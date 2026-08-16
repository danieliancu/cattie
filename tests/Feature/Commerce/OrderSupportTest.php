<?php

namespace Tests\Feature\Commerce;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderSupportRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderSupportTest extends TestCase
{
    use RefreshDatabase;

    // --- Page / navigation ---------------------------------------------------

    public function test_public_customer_can_open_order_support_page(): void
    {
        $this->get(route('order-support.create'))->assertOk()->assertSee('Something wrong with your order?');
    }

    public function test_desktop_and_mobile_navigation_contain_visually_distinct_order_support_link(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        // Desktop nav, mobile nav, and the footer's Customer Service list.
        $this->assertSame(3, substr_count($html, 'Order Support'));
        $this->assertMatchesRegularExpression('/class="[^"]*text-coral[^"]*underline[^"]*"[^>]*>.*?Order Support/s', $html);
    }

    public function test_footer_contains_order_support_link(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('order-support.create'), false);
    }

    public function test_order_support_page_uses_kattie_branding(): void
    {
        $this->get(route('order-support.create'))->assertOk()->assertSee('Kattie.uk')->assertDontSee('Cattie.uk');
    }

    // --- Authenticated customer ------------------------------------------------

    public function test_logged_in_customer_sees_only_their_own_orders(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);
        $mine = Order::factory()->for($owner)->create(['number' => 'CAT-MINE-1']);
        Order::factory()->for($other)->create(['number' => 'CAT-OTHER-1']);

        $this->actingAs($owner)->get(route('order-support.create'))
            ->assertOk()->assertSee('CAT-MINE-1')->assertDontSee('CAT-OTHER-1');
    }

    public function test_own_order_can_be_preselected_through_query_parameter(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        Order::factory()->for($owner)->create(['number' => 'CAT-MINE-2']);
        $other = User::factory()->create(['is_admin' => false]);
        Order::factory()->for($other)->create(['number' => 'CAT-OTHER-2']);

        $html = $this->actingAs($owner)->get(route('order-support.create', ['order' => 'CAT-MINE-2']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="CAT-MINE-2"[^>]*selected/', $html);
    }

    public function test_another_users_order_cannot_be_preselected_via_query_parameter(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        Order::factory()->for($owner)->create(['number' => 'CAT-MINE-3']);
        $other = User::factory()->create(['is_admin' => false]);
        Order::factory()->for($other)->create(['number' => 'CAT-OTHER-3']);

        $html = $this->actingAs($owner)->get(route('order-support.create', ['order' => 'CAT-OTHER-3']))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/value="CAT-OTHER-3"/', $html);
    }

    public function test_tampering_post_order_number_to_another_users_order_is_rejected(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);
        Order::factory()->for($other)->create(['number' => 'CAT-VICTIM']);

        $this->actingAs($owner)->post(route('order-support.store'), [
            'order_number' => 'CAT-VICTIM',
            'message' => 'This is not my order but I am trying anyway.',
        ])->assertSessionHasErrors('order_number');

        $this->assertSame(0, OrderSupportRequest::query()->count());
    }

    public function test_account_order_detail_contains_get_help_cta(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $order = Order::factory()->for($owner)->create(['number' => 'CAT-DETAIL-1']);

        $this->actingAs($owner)->get(route('account.orders.show', $order->number))
            ->assertOk()->assertSee('Get help with this order');
    }

    public function test_account_order_cta_links_to_order_support_with_order_preselected(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $order = Order::factory()->for($owner)->create(['number' => 'CAT-DETAIL-2']);

        $this->actingAs($owner)->get(route('account.orders.show', $order->number))
            ->assertOk()->assertSee(route('order-support.create', ['order' => $order->number]), false);
    }

    // --- Guest -------------------------------------------------------------

    public function test_guest_can_submit_for_valid_order_with_matching_email(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-GUEST-1', 'email' => 'guest@example.com', 'access_token_hash' => hash('sha256', 'unused')]);

        $response = $this->post(route('order-support.store'), [
            'order_number' => 'CAT-GUEST-1',
            'email' => 'GUEST@example.com',
            'message' => 'The wrong item arrived in my parcel.',
        ]);

        $response->assertRedirect(route('order-support.submitted'));
        $this->assertSame(1, OrderSupportRequest::query()->where('order_id', $order->id)->count());
    }

    public function test_guest_with_valid_guest_context_can_submit_without_matching_email(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-GUEST-2', 'email' => 'real-owner@example.com', 'access_token_hash' => hash('sha256', 'guest-token')]);

        $response = $this->withCookie('cattie_guest_token', 'guest-token')->post(route('order-support.store'), [
            'order_number' => 'CAT-GUEST-2',
            'email' => 'someone-else@example.com',
            'message' => 'The box arrived crushed in transit.',
        ]);

        $response->assertRedirect(route('order-support.submitted'));
        $this->assertSame(1, OrderSupportRequest::query()->where('order_id', $order->id)->count());
    }

    public function test_incorrect_email_order_combination_is_rejected(): void
    {
        Order::factory()->create(['number' => 'CAT-GUEST-3', 'email' => 'real-owner@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-GUEST-3',
            'email' => 'wrong@example.com',
            'message' => 'Trying to guess my way in here.',
        ])->assertSessionHasErrors('order_details');

        $this->assertSame(0, OrderSupportRequest::query()->count());
    }

    public function test_nonexistent_order_produces_same_generic_failure_as_wrong_email(): void
    {
        $response = $this->post(route('order-support.store'), [
            'order_number' => 'CAT-DOES-NOT-EXIST',
            'email' => 'anyone@example.com',
            'message' => 'Testing a made up order number.',
        ]);

        $response->assertSessionHasErrors('order_details');
        $this->assertSame(
            "We couldn't match those order details. Please check your order number and email address.",
            session('errors')->first('order_details')
        );
    }

    public function test_error_response_does_not_reveal_whether_order_exists(): void
    {
        Order::factory()->create(['number' => 'CAT-REAL', 'email' => 'real@example.com']);
        $genericMessage = "We couldn't match those order details. Please check your order number and email address.";

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-REAL', 'email' => 'wrong@example.com', 'message' => 'Message one is long enough.',
        ])->assertSessionHasErrors(['order_details' => $genericMessage]);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-DOES-NOT-EXIST', 'email' => 'wrong@example.com', 'message' => 'Message two is long enough.',
        ])->assertSessionHasErrors(['order_details' => $genericMessage]);
    }

    public function test_guest_cannot_access_private_order_information_during_verification(): void
    {
        Order::factory()->create(['number' => 'CAT-PRIVATE', 'email' => 'real@example.com']);

        $response = $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PRIVATE',
            'email' => 'wrong@example.com',
            'message' => 'Should not see order details in this response.',
        ]);

        $response->assertSessionHasErrors('order_details');
        $response->assertDontSee('real@example.com');
        $response->assertDontSee('SW1A 1AA');
    }

    // --- Support request persistence ---------------------------------------

    public function test_valid_submission_creates_order_support_request(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-PERSIST-1', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PERSIST-1', 'email' => 'guest@example.com', 'message' => 'Something arrived broken sadly.',
        ]);

        $this->assertSame(1, OrderSupportRequest::query()->count());
    }

    public function test_support_request_links_to_correct_order(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-PERSIST-2', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PERSIST-2', 'email' => 'guest@example.com', 'message' => 'Linking to the right order please.',
        ]);

        $this->assertSame($order->id, OrderSupportRequest::query()->first()->order_id);
    }

    public function test_authenticated_submission_stores_correct_user_relation(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $order = Order::factory()->for($user)->create(['number' => 'CAT-PERSIST-3']);

        $this->actingAs($user)->post(route('order-support.store'), [
            'order_number' => 'CAT-PERSIST-3', 'message' => 'This should be linked to my account.',
        ]);

        $this->assertSame($user->id, OrderSupportRequest::query()->first()->user_id);
    }

    public function test_guest_submission_leaves_user_id_nullable(): void
    {
        Order::factory()->create(['number' => 'CAT-PERSIST-4', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PERSIST-4', 'email' => 'guest@example.com', 'message' => 'No account attached to this one.',
        ]);

        $this->assertNull(OrderSupportRequest::query()->first()->user_id);
    }

    public function test_contact_email_snapshot_is_stored_correctly(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'email' => 'account-owner@example.com']);
        $order = Order::factory()->for($user)->create(['number' => 'CAT-PERSIST-5']);

        $this->actingAs($user)->post(route('order-support.store'), [
            'order_number' => 'CAT-PERSIST-5', 'message' => 'Checking the stored contact email.',
        ]);

        $this->assertSame('account-owner@example.com', OrderSupportRequest::query()->first()->contact_email);
    }

    public function test_default_status_is_open(): void
    {
        Order::factory()->create(['number' => 'CAT-PERSIST-6', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PERSIST-6', 'email' => 'guest@example.com', 'message' => 'Default status check please.',
        ]);

        $this->assertSame(\App\Enums\OrderSupportStatus::Open, OrderSupportRequest::query()->first()->status);
    }

    public function test_customer_facing_reference_is_unique(): void
    {
        Order::factory()->create(['number' => 'CAT-PERSIST-7', 'email' => 'guest@example.com']);
        Order::factory()->create(['number' => 'CAT-PERSIST-8', 'email' => 'guest2@example.com']);

        $this->post(route('order-support.store'), ['order_number' => 'CAT-PERSIST-7', 'email' => 'guest@example.com', 'message' => 'First submission message.']);
        $this->post(route('order-support.store'), ['order_number' => 'CAT-PERSIST-8', 'email' => 'guest2@example.com', 'message' => 'Second submission message.']);

        $references = OrderSupportRequest::query()->pluck('reference');
        $this->assertCount(2, $references->unique());
        $this->assertMatchesRegularExpression('/^SUP-[A-Z0-9]{6}$/', $references->first());
    }

    public function test_message_is_persisted_as_plain_text(): void
    {
        Order::factory()->create(['number' => 'CAT-PERSIST-9', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PERSIST-9', 'email' => 'guest@example.com', 'message' => '<script>alert(1)</script> plain text please',
        ]);

        $this->assertSame('<script>alert(1)</script> plain text please', OrderSupportRequest::query()->first()->message);
    }

    // --- Photo ---------------------------------------------------------------

    public function test_jpeg_upload_is_accepted(): void
    {
        Storage::fake('local');
        Order::factory()->create(['number' => 'CAT-PHOTO-1', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PHOTO-1', 'email' => 'guest@example.com', 'message' => 'Photo attached of the damage.',
            'photo' => UploadedFile::fake()->image('damage.jpg', 100, 100),
        ])->assertRedirect(route('order-support.submitted'));

        $this->assertNotNull(OrderSupportRequest::query()->first()->photo_storage_key);
    }

    public function test_png_upload_is_accepted(): void
    {
        Storage::fake('local');
        Order::factory()->create(['number' => 'CAT-PHOTO-2', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PHOTO-2', 'email' => 'guest@example.com', 'message' => 'Photo attached of the damage.',
            'photo' => UploadedFile::fake()->image('damage.png', 100, 100),
        ])->assertRedirect(route('order-support.submitted'));

        $this->assertSame('image/png', OrderSupportRequest::query()->first()->photo_mime_type);
    }

    public function test_webp_upload_is_accepted(): void
    {
        Storage::fake('local');
        Order::factory()->create(['number' => 'CAT-PHOTO-3', 'email' => 'guest@example.com']);
        $webp = UploadedFile::fake()->createWithContent('damage.webp', $this->fakeWebpBytes());

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PHOTO-3', 'email' => 'guest@example.com', 'message' => 'Photo attached of the damage.',
            'photo' => $webp,
        ])->assertRedirect(route('order-support.submitted'));

        $this->assertSame('image/webp', OrderSupportRequest::query()->first()->photo_mime_type);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('local');
        Order::factory()->create(['number' => 'CAT-PHOTO-4', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PHOTO-4', 'email' => 'guest@example.com', 'message' => 'Not a real image here.',
            'photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('photo');

        $this->assertSame(0, OrderSupportRequest::query()->count());
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('local');
        Order::factory()->create(['number' => 'CAT-PHOTO-5', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PHOTO-5', 'email' => 'guest@example.com', 'message' => 'A very large photo attached.',
            'photo' => UploadedFile::fake()->image('big.jpg')->size(10241),
        ])->assertSessionHasErrors('photo');

        $this->assertSame(0, OrderSupportRequest::query()->count());
    }

    public function test_photo_is_stored_on_private_disk_with_random_key(): void
    {
        Storage::fake('local');
        Order::factory()->create(['number' => 'CAT-PHOTO-6', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PHOTO-6', 'email' => 'guest@example.com', 'message' => 'Storage key check please.',
            'photo' => UploadedFile::fake()->image('original-filename.jpg', 100, 100),
        ]);

        $request = OrderSupportRequest::query()->first();
        Storage::disk('local')->assertExists($request->photo_storage_key);
        $this->assertStringNotContainsString('original-filename', $request->photo_storage_key);
        $this->assertSame('local', $request->photo_disk);
    }

    public function test_normal_customer_cannot_retrieve_another_support_requests_photo(): void
    {
        Storage::fake('local');
        Order::factory()->create(['number' => 'CAT-PHOTO-7', 'email' => 'guest@example.com']);
        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-PHOTO-7', 'email' => 'guest@example.com', 'message' => 'Photo access control check.',
            'photo' => UploadedFile::fake()->image('secret.jpg', 100, 100),
        ]);
        $request = OrderSupportRequest::query()->first();

        $this->get(route('admin.order-support.photo', $request))->assertRedirect(route('login'));

        $other = User::factory()->create(['is_admin' => false]);
        $this->actingAs($other)->get(route('admin.order-support.photo', $request))->assertForbidden();
    }

    // --- Regression -------------------------------------------------------

    public function test_submitting_support_does_not_mutate_order_totals(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-REGRESSION-1', 'email' => 'guest@example.com', 'total_minor' => 2300]);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-REGRESSION-1', 'email' => 'guest@example.com', 'message' => 'Should not touch totals at all.',
        ]);

        $this->assertSame(2300, $order->fresh()->total_minor);
    }

    public function test_submitting_support_does_not_mutate_order_shipping_address(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-REGRESSION-2', 'email' => 'guest@example.com']);
        $originalAddress = $order->shipping_address;

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-REGRESSION-2', 'email' => 'guest@example.com', 'message' => 'Should not touch the address at all.',
        ]);

        $this->assertSame($originalAddress, $order->fresh()->shipping_address);
    }

    public function test_submitting_support_does_not_change_order_status(): void
    {
        $order = Order::factory()->create(['number' => 'CAT-REGRESSION-3', 'email' => 'guest@example.com', 'status' => OrderStatus::Paid]);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-REGRESSION-3', 'email' => 'guest@example.com', 'message' => 'Should not touch the status at all.',
        ]);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_submitting_support_does_not_create_a_payment(): void
    {
        Order::factory()->create(['number' => 'CAT-REGRESSION-4', 'email' => 'guest@example.com']);

        $this->post(route('order-support.store'), [
            'order_number' => 'CAT-REGRESSION-4', 'email' => 'guest@example.com', 'message' => 'Should not trigger any payment.',
        ]);

        $this->assertSame(0, Payment::query()->count());
    }

    private function fakeWebpBytes(): string
    {
        // Minimal 1x1 lossless WebP image.
        return base64_decode('UklGRhwAAABXRUJQVlA4TA8AAAAvAAAAEAcQEQwAAAaAAA==');
    }
}
