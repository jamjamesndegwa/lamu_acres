# Cloudflare R2 Storage Deployment & Operations Guide

## Overview
Lamu Acres uses **Cloudflare R2** object storage for all persistent media and documents via Laravel's native S3-compatible filesystem driver (`league/flysystem-aws-s3-v3`).

Public property photos and derivatives are served directly from Cloudflare R2 custom domain/public bucket URLs without application-level proxying. Private documents (ownership proof, title deeds, KYC, verification evidence, broker referrals, and intelligence files) are protected with role-based authorization and delivered to authenticated users via short-lived signed URLs.

---

## 1. Cloudflare R2 Bucket Configuration

### A. Create R2 Bucket
In the Cloudflare Dashboard (**R2 Object Storage**), create one bucket (e.g. `lamuacres`):
- Enable **Public access** or connect a custom domain (e.g. `https://media.lamuacres.co.ke` or use the assigned `https://pub-*.r2.dev` URL) for listing photos and derivatives.
- Configure CORS policy allowing `GET` requests from application domains.
- Private uploads (KYC, deeds, evidence) use the **same bucket** with S3 private visibility; access is via signed URLs in the app.

### B. Generate API Tokens
1. In Cloudflare Dashboard, go to **R2** > **Manage R2 API Tokens**.
2. Click **Create API Token**.
3. Permissions: **Object Read & Write**.
4. Specify TTL or leave indefinite according to your security policy.
5. Copy the generated **Access Key ID**, **Secret Access Key**, and **Endpoint URL** (`https://<ACCOUNT_ID>.r2.cloudflarestorage.com`).

---

## 2. Environment Variables (.env)

Set **only** these five variables. Do **not** add `CLOUDFLARE_R2_PRIVATE_*` keys (especially not duplicate keys with empty values — they break `php artisan config:cache` and cause “non-empty parameter: Bucket” upload errors).

```dotenv
CLOUDFLARE_R2_ENDPOINT="https://<ACCOUNT_ID>.r2.cloudflarestorage.com"
CLOUDFLARE_R2_ACCESS_KEY_ID="<YOUR_R2_ACCESS_KEY_ID>"
CLOUDFLARE_R2_SECRET_ACCESS_KEY="<YOUR_R2_SECRET_ACCESS_KEY>"
CLOUDFLARE_R2_BUCKET_NAME="<YOUR_R2_BUCKET_NAME>"
CLOUDFLARE_R2_PUBLIC_URL="https://media.lamuacres.co.ke"
```

After any `.env` change:

```bash
php artisan config:clear
php artisan config:cache
php artisan storage:verify-r2
```

## 3. Deployment & Migration Steps

### Step 1: Run Migrations
Run the schema updates to add file metadata columns (`mime`, `size_bytes`, `consent_disk`, `evidence_disk`):
```bash
php artisan migrate --force
```

### Step 2: Dry-Run Migration of Legacy Files
Test discovering and validating existing local files without moving anything:
```bash
php artisan storage:migrate-r2 --dry-run
```

### Step 3: Execute Resumable Migration
Migrate existing files from `local` disk to Cloudflare R2:
```bash
php artisan storage:migrate-r2 --batch=100
```
*Note: The migration command is completely idempotent. If interrupted, rerun it to resume. Files already migrated will be skipped.*

### Step 4: Verification
1. Run automated tests:
   ```bash
   php -d memory_limit=512M vendor/bin/phpunit tests/Feature/CloudflareR2StorageTest.php
   ```
2. Verify public listing cards and gallery photos load with the R2 CDN domain.
3. Test uploading a title deed or verification file in seller/staff portals and confirming authorized download redirects.

---

## 4. Rollback & Legacy Cleanup Procedures

### Rollback Strategy
If R2 must be temporarily disengaged:
- Set `CLOUDFLARE_R2_BUCKET_NAME=""` in `.env`.
- The application automatically falls back to local storage disk handling.
- Legacy local files remain intact because `storage:migrate-r2` does not delete source bytes.

### Cleanup Procedure
Only after 30+ days of verified production operation on R2:
1. Back up `storage/app/private` locally or offsite.
2. Remove migrated files from `storage/app/private/property-originals/`, `property-derivatives/`, and `ownership/` to reclaim server disk space.
