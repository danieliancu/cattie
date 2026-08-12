<?php

namespace App\Domain\Artwork\Actions;

use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Enums\DesignTemplateVersionStatus;
use App\Models\DesignTemplateVersion;
use DomainException;
use Illuminate\Support\Facades\DB;

class PublishDesignTemplateVersion
{
    public function __construct(private ValidateDesignTemplateConfiguration $validate, private RecordAdminAudit $audit) {}

    public function handle(DesignTemplateVersion $version): DesignTemplateVersion
    {
        if ($version->status !== DesignTemplateVersionStatus::Draft) {
            throw new DomainException('Only draft versions can be published.');
        }
        $this->validate->handle($version->configuration);
        if ($version->last_test_render_status !== 'succeeded') {
            throw new DomainException('A successful test render is required.');
        }

        return DB::transaction(function () use ($version) {
            $version->template->versions()->where('status', DesignTemplateVersionStatus::Published)->update(['status' => DesignTemplateVersionStatus::Retired]);
            $version->update(['status' => DesignTemplateVersionStatus::Published, 'published_at' => now()]);
            $this->audit->handle('template.published', $version);

            return $version->refresh();
        });
    }
}
