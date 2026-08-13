# Real OpenAI artwork generation

Cattie uses its existing private upload and queued generation pipeline in both modes:

- `AI_IMAGE_PROVIDER=fake` for local development and automated tests.
- `AI_IMAGE_PROVIDER=openai` for explicit real development and production generation.

## Runtime configuration

Set these values in the deployment environment:

```dotenv
AI_IMAGE_PROVIDER=openai
OPENAI_API_KEY=
AI_IMAGE_MODEL=gpt-image-2
AI_IMAGE_QUALITY=medium
AI_IMAGE_SIZE=1024x1536
AI_BACKGROUND_REMOVAL_PYTHON=python
```

The OpenAI adapter calls `POST /v1/images/edits` with the normalised customer photo. Storybook Cartoon v3 also sends the versioned internal style reference as the second `image[]`: the customer photo is content-only and the second image is style-only. Its key, role, MIME type, and SHA-256 checksum are frozen into the immutable generation. GPT Image 2 handles image inputs at high fidelity and returns PNG bytes. Because GPT Image 2 does not support transparent backgrounds, the private output is processed locally before product composition.

Install and prewarm the local CPU background-removal runtime on every queue worker:

```powershell
python -m pip install -r requirements-ai.txt
python -c "from rembg import new_session; new_session('isnet-general-use')"
```

The prewarm downloads the segmentation model into the runtime user's model cache. Production workers must use the same user/cache or a pre-populated cache. Customer photographs and generated images remain in Laravel's private local disk; the segmentation step does not call another external service.

After configuration changes, clear cached Laravel configuration and run a queue worker:

```powershell
php artisan optimize:clear
php artisan queue:work --tries=2 --timeout=240
```

If `AI_IMAGE_PROVIDER=openai` is selected without `OPENAI_API_KEY`, generation fails safely and never falls back to fake artwork.

## Explicit paid smoke test

Run exactly one real generation through the existing session, upload, normalisation, generation, background-removal, and composition pipeline:

```powershell
php artisan artwork:openai-smoke "C:\path\to\photo.jpg" --product=water-bottle-with-red-flip-lid --style=storybook-cartoon --name=Test
```

The command reports only safe identifiers and metadata. It does not print the API key or private storage paths.

## Browser acceptance

1. Configure the real provider and start Laravel plus a queue worker.
2. Open `/products/water-bottle-with-red-flip-lid`.
3. Select a variant, enter the child's name, upload a valid real photo, and submit.
4. Wait for polling to show the composed bottle preview.
5. Verify the character is recognisable, full-body, isolated, and integrated without an opaque rectangle.
6. Regenerate and verify a new Generation record and assets are created.
7. Approve one result and verify the approved composed design remains selected.

Real API and visual acceptance tests are intentionally manual and paid. Automated tests fake the OpenAI HTTP response and local segmentation runner and never require internet access.
