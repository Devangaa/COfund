# Auth Module API

Authentication, registration, password management, and user session APIs.

## Architecture

The auth module is built on Laravel Sanctum for API token authentication. It uses a dedicated `AuthService` to centralize business logic, with validation handled by dedicated Form Request classes. Rate limiting is applied per-endpoint via middleware configured in `RouteServiceProvider`.

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/AuthController.php` | Handles registration, login, logout, password reset |
| Service | `app/Services/AuthService.php` | Business logic for user creation, authentication, session management |
| Requests | `app/Http/Requests/{LoginRequest, RegisterRequest, ForgotPasswordRequest, ResetPasswordRequest}.php` | Validation rules per endpoint |
| Model | `app/Models/User.php` | User entity with `HasApiTokens`, `MustVerifyEmail` |
| Middleware | `app/Http/Middleware/Authenticate.php`, `VerifyEmail.php` | Authentication & email verification gates |

### Flow

```
User → Form Request Validation → AuthService Method → Event/Token → Response
```

- **Register**: Validates input → Hashes password → Creates user (role=backer, balance=0) → Fires `Registered` event → Returns user + token.
- **Login**: Validates credentials → `Auth::attempt()` → Creates Sanctum token → Returns user + token.
- **Logout**: Deletes current access token → Returns success message.

## File Structure

```
app/
├── Http/Controllers/Api/AuthController.php
├── Services/AuthService.php
├── Http/Requests/
│   ├── LoginRequest.php
│   ├── RegisterRequest.php
│   ├── ForgotPasswordRequest.php
│   └── ResetPasswordRequest.php
└── Models/User.php
```

## Rate Limiting

Configured in `RouteServiceProvider::configureRateLimiting()`:

| Endpoint | Limiter | Rate | Scope |
|----------|---------|------|-------|
| `POST /api/register` | `register` | 3 requests / minute | Per IP |
| `POST /api/login` | `login` | 5 requests / minute | Per email + IP |
| `POST /api/forgot-password` | `password.request` | 5 requests / minute | Per email + IP |
| `POST /api/reset-password` | `password.request` | 5 requests / minute | Per email + IP |

When a rate limit is exceeded, a `429 Too Many Requests` response is returned with a structured JSON body:

```json
{
  "message": "Too many registration attempts. Please try again in 60 seconds."
}
```

## API Endpoints

### 1. Register

Creates a new user account (defaults to `backer` role, `balance = 0`).

**Endpoint:** `POST /api/register`  
**Middleware:** `public` + `throttle:register`  
**Description:** Registers a new user and returns authentication token. Requires email verification.

#### Request

```
POST /api/register
Content-Type: application/json
Accept: application/json
```

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `name` | string | Yes | `required, string, max:255` | Full name of the user |
| `email` | string | Yes | `required, email, unique:users` | User's email address |
| `password` | string | Yes | `required, string, min:8, confirmed` | Password (must match `password_confirmation`) |
| `password_confirmation` | string | Yes | — | Must match `password` |

#### Example Request

```json
{
  "name": "Ahmad Fauzi",
  "email": "fauzi@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Response (Success: 201)

```json
{
  "user": {
    "id": 3,
    "name": "Ahmad Fauzi",
    "email": "fauzi@example.com",
    "role": "backer",
    "balance": "0.00",
    "email_verified_at": null,
    "is_suspended": false
  },
  "token": "5|9e7a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 400 | `Validation error` | Email already registered / password < 8 chars / mismatch |
| 429 | `Too many registration attempts...` | Rate limited |

---

### 2. Login

Authenticates a user and issues a Sanctum API token.

**Endpoint:** `POST /api/login`  
**Middleware:** `public` + `throttle:login`  
**Description:** Issues a bearer token for authenticated session.

#### Request

```
POST /api/login
Content-Type: application/json
Accept: application/json
```

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `email` | string | Yes | `required, email` | User's email |
| `password` | string | Yes | `required, string` | User's password |

#### Example Request

```json
{
  "email": "fauzi@example.com",
  "password": "password123"
}
```

#### Response (Success: 200)

```json
{
  "user": {
    "id": 3,
    "name": "Ahmad Fauzi",
    "email": "fauzi@example.com",
    "role": "backer",
    "balance": "500000.00",
    "email_verified_at": "2026-08-24T10:00:00.000000Z",
    "is_suspended": false
  },
  "token": "5|9e7a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | `Email or password is incorrect` | Invalid credentials |
| 429 | `Too many login attempts...` | Rate limited |

---

### 3. Logout

Revokes the current access token.

**Endpoint:** `POST /api/logout`  
**Middleware:** `auth:sanctum`  
**Description:** Invalidates the current bearer token.

#### Request

```
POST /api/logout
Authorization: Bearer {token}
Accept: application/json
```

#### Response (Success: 200)

```json
{
  "message": "Logged out successfully"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | `Unauthenticated` | Missing or invalid token |

---

### 4. Get Authenticated User

Returns the currently authenticated user's data.

**Endpoint:** `GET /api/me`  
**Middleware:** `auth:sanctum`  
**Description:** Fetches the profile of the currently logged-in user.

#### Response (Success: 200)

```json
{
  "id": 3,
  "name": "Ahmad Fauzi",
  "email": "fauzi@example.com",
  "role": "backer",
  "balance": "500000.00",
  "email_verified_at": "2026-08-24T10:00:00.000000Z",
  "is_suspended": false
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | `Unauthenticated` | Missing or invalid token |

---

### 5. Send Password Reset Link

Sends a password reset link to the user's email.

**Endpoint:** `POST /api/forgot-password`  
**Middleware:** `public` + `throttle:password.request`  
**Description:** Sends a password reset link via email.

#### Request

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `email` | string | Yes | `required, email` | User's email address |
| `token` | string | Yes | — | Reset token (from email link) |
| `password` | string | Yes | `required, string, min:8, confirmed` | New password |
| `password_confirmation` | string | Yes | — | Must match `password` |

#### Example Request

```json
{
  "email": "fauzi@example.com"
}
```

#### Response (Success: 200)

```json
{
  "message": "If the email exists in our system, a reset link has been sent."
}
```

> **Note:** The response is the same whether or not the email exists, to prevent user enumeration.

---

### 6. Reset Password

Resets the user's password using a valid token.

**Endpoint:** `POST /api/reset-password`  
**Middleware:** `public` + `throttle:password.request`  
**Description:** Resets password using a valid reset token.

#### Request

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `email` | string | Yes | `required, email` | User's email address |
| `token` | string | Yes | — | Reset token (from email link) |
| `password` | string | Yes | `required, string, min:8, confirmed` | New password |
| `password_confirmation` | string | Yes | — | Must match `password` |

#### Example Request

```json
{
  "email": "fauzi@example.com",
  "token": "aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

#### Response (Success: 200)

```json
{
  "message": "Password has been reset successfully"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 400 | `Validation error` | Token invalid or expired / password < 8 chars |
| 429 | `Too many password reset attempts...` | Rate limited |

---

### 7. Resend Email Verification

Sends a new email verification notification.

**Endpoint:** `POST /api/email/resend`  
**Middleware:** `auth:sanctum`  
**Description:** Re-sends the email verification notification.

#### Response (Success: 200)

| SC | Message | Kondisi |
|----|---------|---------|
| 200 | `Verification email resent.` | Success |
| 400 | `Already verified` | Email already verified |

---

### 8. Verify Email

Marks the user's email as verified.

**Endpoint:** `GET /api/email/verify/{id}/{hash}`  
**Middleware:** `signed` + `throttle:6,1`  
**Description:** Verifies the user's email using the signed URL.

#### Response (Success: 200)

```json
{
  "message": "Email successfully verified."
}
```

---

## User Resource Schema

```json
{
  "id": 3,
  "name": "Ahmad Fauzi",
  "email": "fauzi@example.com",
  "role": "backer",
  "balance": "500000.00",
  "email_verified_at": "2026-08-24T10:00:00.000000Z",
  "is_suspended": false
}
```

### Field Reference

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique identifier |
| `name` | string | Full name |
| `email` | string | Email address (unique) |
| `role` | enum | One of `backer`, `creator`, `admin` |
| `balance` | decimal | Account balance (format: decimal:2) |
| `email_verified_at` | datetime\|null | Email verification timestamp (null if unverified) |
| `is_suspended` | boolean | Whether the user is suspended |

## Postman Testing

Import the provided Postman collection `CoFund-API.postman_collection.json` and use the following environment variables:

| Variable | Value |
|----------|-------|
| `base_url` | `http://localhost:8000/api` |

### Test Scripts (Auth)

#### Test 1: Register New User

1. Set request: `POST {{base_url}}/register`
2. Body (raw JSON):
   ```json
   {
     "name": "Test User",
     "email": "testuser@example.com",
     "password": "password123",
     "password_confirmation": "password123"
   }
   ```
3. Expected: `201 Created` with user + token.
4. Save the `token` from response to `{{auth_token}}` environment variable.

#### Test 2: Login with Registered User

1. Set request: `POST {{base_url}}/login`
2. Body (raw JSON):
   ```json
   {
     "email": "testuser@example.com",
     "password": "password123"
   }
   ```
3. Expected: `200 OK` with user + token.
4. Update `{{auth_token}}` with new token.

#### Test 3: Access Protected Resource

1. Set request: `GET {{base_url}}/me`
2. Headers: `Authorization: Bearer {{auth_token}}`
3. Expected: `200 OK` with user data.

#### Test 4: Rate Limit Login

1. Hit `POST {{base_url}}/login` with wrong credentials 6 times rapidly.
2. Expected: `429 Too Many Requests` after 5 attempts within 1 minute.

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | Register with valid data | Valid name, email, password | 201 + user + token |
| 2 | Register with duplicate email | Existing email | 400 validation error |
| 3 | Register with short password | Password < 8 chars | 400 validation error |
| 4 | Register with mismatched password | password != password_confirmation | 400 validation error |
| 5 | Login with valid credentials | Correct email + password | 200 + user + token |
| 6 | Login with invalid credentials | Wrong password | 401 unauthorized |
| 7 | Logout with valid token | Bearer token | 200 success message |
| 8 | Access /me without token | No Authorization header | 401 unauthenticated |
| 9 | Forgot password with valid email | Valid email | 200 generic message |
| 10 | Forgot password with invalid email | Non-existent email | 200 generic message (same as valid) |
| 11 | Reset password with invalid token | Bad/expired token | 400 validation error |
| 12 | Reset password with mismatched password | password != confirmation | 400 validation error |

## Troubleshooting

### 1. Getting 401 "Unauthenticated" on every request

This is because Sanctum relies on either:
- **Bearer Token**: Client sends `Authorization: Bearer {token}` header.
- **Cookie-based**: Frontend running on a *stateful domain* (see `config/sanctum.php` `stateful` array).

Since `EnsureFrontendRequestsAreStateful` middleware is **commented out** in `routes/api.php`, only bearer tokens are accepted. Make sure the `Authorization` header is sent.

### 2. Rate Limit Exceeded (429)

Wait for the cooldown period to elapse. The rate limiter keys differ per endpoint:
- Register: per IP address.
- Login: per `email + IP`.
- Password: per `email + IP`.

### 3. Email Verification Not Working

Verify that `MAIL_MAILER` is set in `.env` (defaults to `smtp` with mailpit at port 1025). Check that `MAIL_FROM_ADDRESS` is valid. The verification email is only sent during registration via the `Registered` event → `SendEmailVerificationNotification` listener.

### 4. Reset Password Token Invalid/Expired

Password reset tokens expire after `env('PASSWORD_RESET_TTL', 60)` minutes (default 60). Ensure the token is used within this window.

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| Register | Public | `throttle:register` |
| Login | Public | `throttle:login` |
| Logout | Authenticated | `auth:sanctum` |
| View Profile (`/me`) | Authenticated | `auth:sanctum` |
| Resend Verification | Authenticated + Unverified | `auth:sanctum` |
| Verify Email | Public (signed) | `signed, throttle:6,1` |
| Forgot Password | Public | `throttle:password.request` |
| Reset Password | Public | `throttle:password.request` |
