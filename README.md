# 🔐 PKL REST API Auth For WordPress

A lightweight WordPress plugin that controls access to the REST API by requiring user authentication.  
This helps improve security by preventing unauthorized access to REST API endpoints for non-logged-in or not registered users.

#### 🌐 For WordPress REST API URL Endpoint
> ./wp-json/<WP_REST_API_ENDPOINT>

---

## ✨ Features

- 🔒 Restricts REST API access to logged-in or registered users only.
- 🚫 Blocks unauthenticated requests with customizable settings.
- ⚙️ Provides admin settings page to enable/disable authentication requirement.
- 🌍 Multilingual support with WordPress text domain.
- ✨ Simple and lightweight, no external dependencies.

---

## 📝 WordPress Requirements

- **WordPress:** 5.0 or higher
- **Tested up to:** 6.8
- **PHP:** 7.4 or higher

---

## 📥 Installation

1. Download or clone this repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/PalmiizKittinan/pkl-rest-api-auth.git
   ```
2. Activate the plugin via the **WordPress Admin Dashboard** → **Plugins**.
3. Go to **Settings** → **PKL REST API Auth** to configure authentication settings.

---

## 🚀 Usage

- When enabled, the plugin blocks unauthenticated access to the WordPress REST API.
- If a non-logged-in user tries to access the API, they will receive a `401 Unauthorized` error:

```json
{
  "code": "rest_not_logged_in",
  "message": "You are not currently logged in.",
  "data": {
    "status": 401
  }
}
```

- If a logged-in user does not have sufficient permissions, they will receive a `403 Forbidden` error:

```json
{
  "code": "rest_insufficient_permissions",
  "message": "You do not have sufficient permissions to access this API.",
  "data": {
    "status": 403
  }
}
```

---

## ⚙️ Settings

Navigate to **Settings → PKL REST API Auth** in your WordPress admin dashboard:

- **Enable REST API Authentication**
    - ✅ Checked: Only logged-in users can access the REST API.
    - ⬜ Unchecked: REST API remains open as usual.

---

## 🛠️ Development

- Clone the repo:
  ```bash
  git clone https://github.com/PalmiizKittinan/pkl-rest-api-auth.git
  ```
- Make your changes.
- Submit a pull request for contributions.

---

## 🌐 For Development API Platform

> Using `<access_tokens>` for All API Endpoint Authentication

### 📖 API Usage Guide

1. 🔐 **Generate Access Token**
    - **Send a POST request to get an access token:**
        - **POST** | https://`<your-wordpress-url>`/wp-json/oauth/token
      ```json
      {
        "email": "user@example.com"
      }
      ```

        - Response (JSON):
      ```json
      {
        "access_token": "abcd1234...",
        "token_type": "Bearer",
        "user": {
          "id": 1,
          "login": "username",
          "email": "user@example.com",
          "display_name": "Display Name"
        },
        "created_at": "2024-01-01 12:00:00",
        "status": "active"
      }
      ```

2. 🚀 **Use Access Token**
    - **Include the access token in your API requests using one of these methods:**

    - **POST** | https://`<your-wordpress-url>`/wp-json/wp/v2/posts

        - Method 1: Authorization Header (Recommended) 👍
          ```markdown
          Headers:
          
          Authorization: Bearer <your_access_token_here>
          ```

        - Method 2: Form-data
          ```markdown
          Form-data:
          
          access_token: <your_access_token_here>
          title: Test Post
          content: Post content here
          status: publish | draft | pending | private | future
          ```

---

## 👤 Author

- **Author:** [Kittinan Lamkaek](https://github.com/PalmiizKittinan)

---

## 📄 License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).  
You are free to modify and redistribute under the same license.
