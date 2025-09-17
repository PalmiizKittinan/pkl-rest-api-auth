# 🔐 PKL WP REST API Auth

A lightweight WordPress plugin that controls access to the REST API by requiring user authentication.  
This helps improve security by preventing unauthorized access to REST API endpoints for non-logged-in users.

---

## ✨ Features

- 🔒 Restricts REST API access to logged-in users only.
- 🚫 Blocks unauthenticated requests with customizable settings.
- ⚙️ Provides admin settings page to enable/disable authentication requirement.
- 🌍 Multilingual support with WordPress text domain.
- 🪶 Simple and lightweight, no external dependencies.

---

## 📋 Requirements

- **WordPress:** 5.0 or higher
- **Tested up to:** 6.6
- **PHP:** 7.4 or higher

---

## 📥 Installation

1. Download or clone this repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/PalmiizKittinan/pkl-wp-rest-api-auth.git
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
  git clone https://github.com/PalmiizKittinan/pkl-wp-rest-api-auth.git
  ```
- Make your changes.
- Submit a pull request for contributions.

### 🌐 For Development API Platform

#### 🛡️ Credential Method with Registered Email

- **Method 1: Form-data (Recommended)**
  ```markdown
  Key: email | Value: user@example.com
  ```
- **Method 2: Header**
  ```markdown
  X-Email: user@example.com
  ```
- **Method 3: Query Parameter**
  ```markdown
  ?email=user@example.com
  ```

---

## 👤 Author

- **Author:** [Kittinan Lamkaek](https://github.com/PalmiizKittinan)

---

## 📄 License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).  
You are free to modify and redistribute under the same license.
