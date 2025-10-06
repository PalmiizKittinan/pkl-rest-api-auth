# 🔐 PKL WPz REST API Authentication

![](https://img.shields.io/badge/Stable_Plugin_Version%20-v1.1.0%20-default)
![](https://img.shields.io/badge/Manual_Version%20-1.3.2%20-blue)<br>

🔗 WordPress Plugin Public URL: [https://wordpress.org/plugins/pkl-wpz-rest-api-auth](https://wordpress.org/plugins/pkl-wpz-rest-api-auth/)

---

## 📝 Description

PKL WPz REST API Authentication provides secure authentication for [WordPress REST API endpoints](https://developer.wordpress.org/rest-api/) using access tokens. Only registered users can generate tokens and access the API, giving you complete control over who can interact with your
WordPress site programmatically.

## ✨ Features

- 🔒 **Secure Token-based Authentication** - OAuth-style access tokens
- 🛡️ **Multiple Authentication Methods** - Form-data, Headers, Bearer Token, Query Parameters
- 👥 **User Management** - Admin interface to manage all access tokens
- 🔄 **Token Management** - Revoke, restore, or delete tokens
- 📊 **Admin Dashboard** - View all active and revoked tokens
- 🚀 **RESTful API** - Generate tokens via REST endpoint
- 🔧 **Easy Integration** - Works with existing WordPress REST API

## 📝 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Users must be registered in WordPress

## 🚀 Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/pkl-wpz-rest-api-auth/` directory
3. Activate the plugin through WordPress admin
4. Configure settings in **Settings → PKL REST API Auth**

## 🛠️ Configuration

### Plugin Settings

Go to **Settings → PKL REST API Auth** to:

- Enable/disable REST API authentication
- View API usage guide
- Manage access tokens

## 📊 Admin Interface

**The plugin provides three main tabs**

1. **Settings** - Configure authentication requirements
    - Enable/Disable REST API Authentication
2. **Access Tokens** - Manage all user tokens
    - View all generated tokens
    - Revoke tokens (disable access)
    - Restore revoked tokens
    - Delete tokens permanently
3. **API Guide** - Complete usage documentation

---

# 🎯 REST API Quick Start Guide

## 📋 Authentication Method Comparison

| Method              | Security | Use Case      | Pros                  | Cons          |
|---------------------|----------|---------------|-----------------------|---------------|
| **Bearer Token**    | Highest  | Production    | HTTP Standard, Secure | May be logged |
| **Custom Header**   | High     | Internal APIs | Explicit, Clear       | Non-standard  |
| **Form-data**       | Good     | Testing/Files | Easy testing          | Not standard  |
| **Query Parameter** | 🚫 Low   | Development   | Simple                | Security risk |

### Example

#### ✅ Method 1: Authorization Bearer (Recommended for Production)

```text
GET /wp-json/wp/v2/posts
Authorization:Bearer pkl_wpz_abcd1234...
```

#### 🚀 Method 2: Form-data (Recommended for Testing)

```text
POST /wp-json/wp/v2/posts
Content-Type: multipart/form-data

api_key: pkl_wpz_abcd1234...
title: Test Post
content: Post content here
status: draft
```

#### Method 3: Custom Header

```text
GET /wp-json/wp/v2/posts
X-API-Key: pkl_wpz_abcd1234...
```

#### 🚨 Method 4: Query Parameter (Development Only)

```text
GET /wp-json/wp/v2/posts?api_key=pkl_wpz_abcd1234...
```

# 🌐 API Reference

> Use `<your_api_key>` for authenticating all API requests.

## 📖 API Usage Guide

1. 🔐 **Generate API Key**
    - Create an API key under `Users > Profile > REST API Access`.

2. 🚀 **Use API Key**
    - **You can include your API key in requests using one of the following methods:**

- Method 1: **Bearer Token (Recommended)**
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

# 📜 PHPStorm Git Worktree Script for Windows PowerShell

## ⚙️ PowerShell Execution Policy Setting

```text
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

## 💻 Example Development Flows

### 1. Create Worktree Development Branch

- Create Worktree
    ```text
    .\phps-create-worktree.ps1 "dev/2.4.0"   
    .\phps-create-worktree.ps1 "feature/user-auth" "develop"
    ```
- Open Your Worktree Project with PHPStorm

### 2. Merge and Cleanup Worktree (After the development is complete)

```text
.\phps-merge-and-cleanup.ps1 "dev/2.4.0"
```

### 3. Created Git Tag After Merge Finished

```text
# Create Git Tag
git tag -a "v2.4.0" -m "Updated Minor Version" 

# Push Git Tag
git push origin v2.4.0
```

---

## 👨‍💻 Author

- GitHub: [@PalmiizKittinan](https://github.com/PalmiizKittinan)
- Plugin URI: [https://github.com/PalmiizKittinan/pkl-wpz-rest-api-auth](https://wordpress.org/plugins/pkl-wpz-rest-api-auth)

## 📄 License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).  
You are free to modify and redistribute it under the same license.
