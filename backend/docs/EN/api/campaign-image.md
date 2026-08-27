# Campaign Image Module API

Campaign image upload and management.

## Architecture

The Campaign Image module handles the upload, validation, and deletion of campaign images. The first image added becomes the primary image by default. Images are stored on the `campaigns` disk (public/local).

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/CampaignImageController.php` | Handles image upload and deletion |
| Service | `app/Services/CampaignImageService.php` | Business logic for image management |
| Requests | `app/Http/Requests/{StoreCampaignImageRequest, DeleteCampaignImageRequest}.php` | Validation rules |
| Resource | `app/Http/Resources/CampaignImageResource.php` | JSON response formatting |
| Model | `app/Models/CampaignImage.php` | Image entity |

### Flow

```
Creator → StoreCampaignImageRequest
       → CampaignImageService::create()
       → CampaignService::ensureEditable() check
       → Check max 5 images
       → Store file to campaigns disk
       → Create CampaignImage record
       → (if no primary → set as primary)

Creator → DeleteCampaignImageRequest
       → CampaignImageService::deleteMany()
       → CampaignService::ensureEditable() check
       → Lock row count
       → Ensure ≥1 image remains
       → Validate IDs
       → Delete physical files from storage
       → Soft-delete records
```

## File Structure

```
app/
├── Http/Controllers/Api/CampaignImageController.php
├── Services/CampaignImageService.php
├── Http/Requests/
│   ├── StoreCampaignImageRequest.php
│   └── DeleteCampaignImageRequest.php
├── Http/Resources/CampaignImageResource.php
└── Models/CampaignImage.php
```

## API Endpoints

### 1. Upload Image

Uploads a new image to a campaign. The first image becomes primary automatically.

**Endpoint:** `POST /api/campaigns/{slug}/images`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Uploads an image to the campaign.

#### Authorization

User must own the campaign. Must be in editable state (DRAFT status).

#### Request Body (Multipart Form Data)

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `image` | file | Yes | `required, image, mimes:jpeg,png,jpg,gif, max:2048` | Image file (max 2MB) |

#### Example Request

```
POST /api/campaigns/{slug}/images
Content-Type: multipart/form-data
Authorization: Bearer {token}

Body: (form-data)
- image: (file upload)
```

#### Response (Success: 201)

```json
{
  "id": 5,
  "url": "http://localhost/storage/campaigns/IMG-abc123.jpg",
  "is_primary": false,
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

> If this is the first image, `is_primary` will be `true`.

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not the campaign owner |
| 409 | Campaign is not in editable state | Campaign is not DRAFT |
| 422 | Validation error | File too large (>2MB) / not an image / invalid format |
| 422 | Campaign has reached maximum 5 images | Already has 5 images |

---

### 2. Delete Images (Bulk)

Deletes multiple images from a campaign.

**Endpoint:** `DELETE /api/campaigns/{slug}/images`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Removes multiple images in one request.

#### Authorization

User must own the campaign. Must be in editable state (DRAFT status).

#### Request Body

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `ids` | array | Yes | `required, array, min:1` | Array of image IDs |
| `ids.*` | integer | Yes | `integer, exists:campaign_images,id` | Must exist |

#### Example Request

```json
{
  "ids": [5, 6]
}
```

#### Response (Success: 200)

```json
{
  "message": "Images deleted successfully"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not the campaign owner |
| 409 | Campaign is not in editable state | Campaign is not DRAFT |
| 409 | Cannot delete all images | Only 1 image remains and user tries to delete it |
| 422 | Validation error | Invalid image IDs |

## Image Resource Schema

```json
{
  "id": 5,
  "url": "http://localhost/storage/campaigns/IMG-abc123.jpg",
  "is_primary": false,
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

### Field Reference

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Image ID |
| `url` | string | Full URL to the image file |
| `is_primary` | boolean | Whether this is the primary image |
| `created_at` | datetime | Creation timestamp |

## Business Rules

### 1. Maximum 5 Images

A campaign can have a maximum of 5 images. Attempting to upload a 6th image returns:
```json
{
  "message": "Campaign has reached maximum 5 images"
}
```

### 2. Must Keep At Least 1 Image

When deleting images, the system ensures at least 1 image remains. If attempting to delete all images:
- The `campaign_images` table row count is locked
- After deletion, if no images remain, a `ValidationException` is thrown: "Cannot delete all images"

### 3. Primary Image Management

When a campaign is created:
- The first image uploaded is automatically set as `is_primary = true`
- If the primary image is deleted, the first remaining image becomes the new primary

When images are deleted:
- If the deleted image was primary, the first remaining image is promoted to primary (handled in `CampaignService::create` and `destroy`)

### 4. Editable State Only

Images can only be uploaded or deleted when the campaign is in **DRAFT** status. This is enforced by `CampaignService::ensureEditable()`.

### 5. File Storage

Images are stored on the `campaigns` disk (configured as `public` local storage in `config/filesystems.php`). The URL is generated via `Storage::disk('public')->url($path)`.

### 6. File Deletion

When images are deleted:
1. Physical files are deleted from storage using `Storage::disk('campaigns')->delete($url)`
2. Records are soft-deleted (not permanently removed)
3. If the deleted image was primary, the next image is promoted

## Postman Testing

### Test Scripts (Campaign Images)

#### Test 1: Upload Image (Multipart)

1. `POST {{base_url}}/campaigns/{draft-slug}/images`
2. Headers: `Authorization: Bearer {{creator_token}}`
3. Body: `form-data` → key: `image`, type: `file`, select an image file
4. Expected: `201 Created` with image URL.

#### Test 2: First Image is Primary

1. Create a new campaign.
2. Upload one image.
3. Expected: `is_primary = true`.

#### Test 3: Upload 6th Image

1. Upload 5 images.
2. Try to upload a 6th.
3. Expected: `422 Validation error`.

#### Test 4: Delete Images

1. Upload 3 images.
2. `DELETE {{base_url}}/campaigns/{slug}/images`
3. Body:
   ```json
   { "ids": [5, 6] }
   ```
4. Expected: `200 OK`.

#### Test 5: Delete All Images (Only 1 Remaining)

1. Ensure only 1 image remains.
2. Delete it.
3. Expected: `409 Conflict` / `422 Validation error`.

#### Test 6: Upload on Non-DRAFT Campaign

1. Use an ACTIVE campaign slug.
2. Try to upload an image.
3. Expected: `409 Conflict` — "Campaign is not in editable state".

#### Test 7: Access as Non-Owner

1. Use a different creator's token.
2. Try to upload/delete images on another's campaign.
3. Expected: `403 Forbidden`.

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | Upload valid image (first) | File (jpeg/png) | 201 + is_primary=true |
| 2 | Upload valid image (not first) | File | 201 + is_primary=false |
| 3 | Upload too large file (>2MB) | 3MB image | 422 validation error |
| 4 | Upload non-image file | .txt file | 422 validation error |
| 5 | Upload invalid format | .webp/.svg | 422 validation error |
| 6 | Upload 6th image | After 5 exist | 422 "Campaign has reached maximum 5 images" |
| 7 | Delete images (multiple) | 2-3 valid IDs | 200 + deleted |
| 8 | Delete all images (last 1) | Delete last remaining | 409 "Cannot delete all images" |
| 9 | Upload on non-DRAFT campaign | Active/Review campaign | 409 campaign not editable |
| 10 | Upload as non-owner | Other creator's campaign | 403 forbidden |
| 11 | Delete as non-owner | Other creator's images | 403 forbidden |
| 12 | Delete primary image | Primary image ID | 200 + new primary promoted |
| 13 | Upload to invalid campaign slug | Non-existent slug | 404 not found |

## Troubleshooting

### 1. "File too large" error

Files must be ≤ 2MB (specified as `max:2048` in kilobytes).

**Fix:** Compress the image or resize it before uploading. The frontend should show a preview and warn the user before uploading.

---

### 2. "Campaign is not in editable state"

Images can only be added while the campaign is in DRAFT status. Once submitted for review or approved, image management is locked.

**Fix:** Ensure the campaign is in DRAFT status before uploading or deleting images. This status lock applies to all sub-resources (tiers, updates, images).

---

### 3. "Campaign has reached maximum 5 images"

The `CampaignImageService::create()` method checks `if ($campaign->images()->count() >= 5)` before creating a new image.

**Fix:** Delete some existing images before uploading new ones.

---

### 4. "Cannot delete all images"

The `CampaignImageService::deleteMany()` method acquires a row lock, deletes the selected images, and then checks `if ($campaign->images()->count() === 0)`. If so, it throws a `ValidationException`.

Note that `images()` returns only non-deleted records because `CampaignImage` uses the `SoftDeletes` trait. So if you've previously soft-deleted some images, the count will still be accurate.

---

### 5. Image URL doesn't work

The URL is generated by `Storage::disk('public')->url($url)`. Ensure:

1. The `public/storage` symlink exists (`php artisan storage:link`)
2. The web server has read permissions for `storage/app/public`
3. The `.env` `APP_URL` is set correctly (the URL is prefixed with `APP_URL`)

---

### 6. Deleting doesn't remove the file from disk

The `CampaignImageService::deleteMany()` method does call `Storage::disk('campaigns')->delete($image->url)`, which removes the physical file. However, if the method throws a `ValidationException` after deletion (due to the "cannot delete all" check), the physical files are already deleted but the database transaction is **not** rolled back.

This means:
- The physical file IS deleted from disk
- But the DB record is NOT soft-deleted (because the exception was thrown before `$image->delete()` was called)

**Potential bug:** If this edge case fails mid-way, some files may be deleted on disk but records remain in the database, leaving "orphaned" records pointing to non-existent files.

**Fix:** Consider wrapping the entire operation in a DB transaction and deleting files only after the count check passes.

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| Upload image | Creator (owner) | `auth:sanctum, role:creator, verified` |
| Delete image | Creator (owner) | `auth:sanctum, role:creator, verified` |
