# API Documentation (Project/public/api)

This README documents the PHP API endpoints inside `Project/public/api`.

> Base URL (local XAMPP example)
>
> `http://localhost/phpLearning/Project/public/api`

---

## Response format

All endpoints respond with JSON.

### Success

```json
{
  "success": true,
  "...": "additional fields"
}
```

### Error

```json
{
  "success": false,
  "message": "Human readable error message"
}
```

---

## Authentication

### Access token (JWT)

Most protected endpoints call `require_access_token()`.

Your code accepts the access token in **any one** of these places:

1. **Authorization header** (recommended):
   - `Authorization: Bearer <ACCESS_TOKEN>`
2. **JSON body**:
   - `{ "token": "<ACCESS_TOKEN>" }`
3. **Form body** (`multipart/form-data` / `application/x-www-form-urlencoded`):
   - `token=<ACCESS_TOKEN>`

> Note (common on XAMPP/Apache):
> PHP may not populate `$_SERVER['HTTP_AUTHORIZATION']` unless Apache is configured to pass it through.
> If you keep getting `{ "success": false, "message": "Missing token" }` even with the header set, use the **form field `token`** workaround for `multipart/form-data` endpoints, or configure Apache to forward the Authorization header.

### Refresh token

Refresh tokens are sent in JSON body as:

```json
{ "refresh_token": "<REFRESH_TOKEN>" }
```

---

## Endpoints

### Auth

#### POST `/auth/register.php`
Create an account and send OTP to email.

- **Auth**: No
- **Content-Type**: `application/json`
- **Body**:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "mobile": "9876543210",
  "password": "StrongPass@123"
}
```

- **Success (201)**: returns a verification `token` used for OTP verification

```json
{
  "success": true,
  "message": "OTP sent to your email",
  "token": "<VERIFY_TOKEN>"
}
```

---

#### POST `/auth/verify_otp.php`
Verify the OTP sent during registration.

- **Auth**: No
- **Content-Type**: `application/json`
- **Body**:

```json
{
  "token": "<VERIFY_TOKEN>",
  "otp": "123456"
}
```

---

#### POST `/auth/resend_otp.php`
Resend OTP for an inactive/unverified account.

- **Auth**: No
- **Content-Type**: `application/json`
- **Body**:

```json
{
  "token": "<VERIFY_TOKEN>"
}
```

---

#### POST `/auth/login.php`
Login with email and password.

- **Auth**: No
- **Content-Type**: `application/json`
- **Body**:

```json
{
  "email": "john@example.com",
  "password": "StrongPass@123"
}
```

- **Success (200)**: returns tokens

```json
{
  "success": true,
  "message": "Login successful",
  "role": "user",
  "access_token": "<ACCESS_TOKEN>",
  "refresh_token": "<REFRESH_TOKEN>"
}
```

---

#### POST `/auth/refresh.php`
Rotate refresh token and get a new access token.

- **Auth**: No
- **Content-Type**: `application/json`
- **Body**:

```json
{
  "refresh_token": "<REFRESH_TOKEN>"
}
```

- **Success (200)**: returns new tokens

```json
{
  "success": true,
  "access_token": "<NEW_ACCESS_TOKEN>",
  "refresh_token": "<NEW_REFRESH_TOKEN>"
}
```

---

#### POST `/auth/logout.php`
Logout user (optionally revoke refresh token).

- **Auth**: **Yes (Access token required)**
- **Content-Type**: `application/json`
- **Headers**:
  - `Authorization: Bearer <ACCESS_TOKEN>`
- **Body (optional)**:

```json
{
  "refresh_token": "<REFRESH_TOKEN>"
}
```

---

#### POST `/auth/forgot_password.php`
Send password reset link to email (returns generic message even if email doesn’t exist).

- **Auth**: No
- **Content-Type**: `application/json`
- **Body**:

```json
{
  "email": "john@example.com"
}
```

---

#### POST `/auth/reset_password.php`
Reset password using reset token.

- **Auth**: No
- **Content-Type**: `application/json`
- **Body**:

```json
{
  "token": "<RESET_TOKEN>",
  "password": "NewStrongPass@123"
}
```

---

#### POST `/auth/delete_account.php`
Delete currently logged-in account (requires current password).

- **Auth**: **Yes (Access token required)**
- **Content-Type**: `application/json`
- **Headers**:
  - `Authorization: Bearer <ACCESS_TOKEN>`
- **Body**:

```json
{
  "password": "YourCurrentPassword"
}
```

---

### Posts

> All post endpoints require an **access token**.

#### POST `/posts/all_posts.php`
Fetch all posts for the logged-in user. Optionally pass `post_id` to fetch a single post with images.

- **Auth**: **Yes (Access token required)**
- **Content-Type**: `application/json`
- **Headers**:
  - `Authorization: Bearer <ACCESS_TOKEN>`
- **Body (optional)**:

```json
{ "post_id": 123 }
```

- If body is omitted or `post_id` is not provided, it returns all posts for the user.

---

#### POST `/posts/create_post.php`
Create a post with 1–5 images.

- **Auth**: **Yes (Access token required)**
- **Content-Type**: `multipart/form-data`
- **Headers**:
  - `Authorization: Bearer <ACCESS_TOKEN>`
  - (If Authorization header is not reaching PHP on Apache, send a form field `token` as workaround.)
- **Form fields**:
  - `title` (string, 3–150)
  - `description` (string, up to 1000)
  - `post_images[]` (1–5 files, JPG/PNG, max 2MB each)

---

#### POST `/posts/update_post.php`
Update post title/description and manage images.

- **Auth**: **Yes (Access token required)**
- **Content-Type**: `multipart/form-data`
- **Headers**:
  - `Authorization: Bearer <ACCESS_TOKEN>`
  - (Workaround option: form field `token`)

- **Form fields**:
  - `post_id` (required, integer)
  - `title` (optional, 3–150)
  - `description` (optional, up to 1000)

- **Image controls (all optional)**:
  - `keep_images`:
    - Either a comma-separated string of image IDs (e.g. `"12,13"`)
    - Or an array field (depends on your client)
  - `replace_images[<image_id>]`:
    - File(s) keyed by existing image id(s) to replace
  - `new_images[]`:
    - New image files to add

- **Rules**:
  - Final total images (kept + replaced + new) must be **≤ 5**.

---

#### POST `/posts/delete_post.php`
Delete one or more posts for the logged-in user.

- **Auth**: **Yes (Access token required)**
- **Content-Type**: `application/json`
- **Headers**:
  - `Authorization: Bearer <ACCESS_TOKEN>`
- **Body**:

```json
{
  "post_ids": "10,11,12"
}
```

---

## Quick cURL examples

### Login

```bash
curl -X POST "http://localhost/phpLearning/Project/public/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"StrongPass@123"}'
```

### Get all posts

```bash
curl -X POST "http://localhost/phpLearning/Project/public/api/posts/all_posts.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -d '{}' 
```

### Delete posts

```bash
curl -X POST "http://localhost/phpLearning/Project/public/api/posts/delete_post.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -d '{"post_ids":"10,11"}'
```
