# 🔐 PKL REST API Auth For WordPress

A lightweight WordPress plugin that restricts access to the REST API by requiring user authentication.  
It enhances security by preventing unauthorized access to REST API endpoints for users who are not logged in or not registered.

#### 🌐 WordPress REST API URL Endpoint
`https://<your-wordpress-url>/wp-json/wp/v2/<api-endpoint>`

---

## ✨ Features

- 🔒 Restricts REST API access to authenticated (logged-in) or registered users only.
- 🚫 Blocks unauthenticated requests with customizable options.
- ⚙️ Provides an admin settings page to enable or disable authentication requirements.
- 🌍 Multilingual support using WordPress text domains.
- ✨ Lightweight and simple, with no external dependencies.

---

## 📝 Requirements

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
3. Go to **Settings** → **PKL REST API Auth** to configure authentication options.

---

## 🚀 Usage

- When enabled, the plugin blocks all unauthenticated access to the WordPress REST API.
- If a non-logged-in user tries to access the API, they will receive a `401 Unauthorized` response:

```json
{
  "code": "rest_not_logged_in",
  "message": "You are not currently logged in.",
  "data": {
    "status": 401
  }
}
```

- If a logged-in user does not have sufficient permissions, they will receive a `403 Forbidden` response:

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
    - ✅ Checked: Authentication is required for REST API access.
    - ⬜ Unchecked: REST API remains open without restrictions.

---

## 🛠️ Development

- Clone the repository:
  ```bash
  git clone https://github.com/PalmiizKittinan/pkl-rest-api-auth.git
  ```
- Make your modifications.
- Submit a pull request to contribute.

---

## 🌐 API Key Authentication (For Developers)

> Use `<your_api_key>` for authenticating all API requests.

### 📖 API Usage Guide

1. 🔐 **Generate API Key**
    - Create an API key under `Users > Profile > REST API Access`.

2. 🚀 **Use API Key**
    - **You can include your API key in requests using one of the following methods:**

- Method 1: **Bearer Token (Recommended)** 👍
  ```text
  Authorization: Bearer <your_api_key>
  ```

- Method 2: **Header API Key**
  ```text
  X-API-Key: <your_api_key>
  ```

- Method 3: **Form-data**
  ```text
  api_key: <your_api_key>
  title: Test Post
  content: Post content here
  status: publish | draft | pending | private | future
  ```

- Method 4: **Query Parameter**
  ```text
  ?api_key=<your_api_key>
  ```

### 🎯 Example: JSON Request Body
#### Method: POST | `https://<your-wordpress-url>/wp-json/wp/v2/posts`

Authorization Header:
```text
Authorization: Bearer <your_api_key>
```

Request Header:
```text
Content-Type: application/json
```

JSON Body:
```json
{
  "title": "Lorem Ipsum",
  "content": "Maecenas sagittis convallis volutpat.",
  "status": "draft"
}
```

---

## 👤 Author

- **Author:** [Kittinan Lamkaek](https://github.com/PalmiizKittinan)

---

## 📄 License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).  
You are free to modify and redistribute it under the same license.
