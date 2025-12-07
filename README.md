**Store App — Backend Wiring Instructions**

- **Purpose:** Convert the existing frontend pages to use a simple PHP API backed by the MySQL database included in `DATABASEFILE/store_database.sql`.

Getting started (Laragon / Windows):

- Import the database (using phpMyAdmin or MySQL CLI):

```powershell
# from PowerShell, assuming MySQL on default (root, no password for Laragon)
# adjust user/password as needed
mysql -u root < "c:/laragon/www/store_app/DATABASEFILE/store_database.sql"
```

- Ensure the DB credentials in `inc/db.php` match your MySQL instance (host, DB name, user, password).

- Start Laragon (or your Apache + PHP server) and open:
  - `http://localhost/store_app/index.html` — login page (now uses `api/auth.php`)
  - `http://localhost/store_app/products.html` — products management page (talks to `api/products.php`)

API endpoints (JSON):

- `POST api/auth.php` — login
  - Body: `{ "username": "admin", "password": "..." }`
  - Success: `{ "success": true, "data": { userId, username, fullName, role } }`

- `GET api/products.php` — list products
  - Success: `{ "success": true, "data": [ { id, name, description, price, stock, category, barcode, icon, ... }, ... ] }`

- `GET api/products.php?id=ID` — get single product

- `POST api/products.php` — create product
  - Body JSON: `{ name, description, price, stock, category, barcode, icon }`

- `PUT api/products.php?id=ID` — update product
  - Body JSON: fields to update

- `DELETE api/products.php?id=ID` — delete product

Quick curl (PowerShell) examples:

```powershell
# list products
curl http://localhost/store_app/api/products.php | ConvertFrom-Json

# get product 1
curl "http://localhost/store_app/api/products.php?id=1" | ConvertFrom-Json

# create product
curl -Method POST -Uri http://localhost/store_app/api/products.php -Body (ConvertTo-Json @{ name='Test'; price=100; stock=5; category='Maison' }) -ContentType 'application/json'

# update product 1
curl -Method PUT -Uri "http://localhost/store_app/api/products.php?id=1" -Body (ConvertTo-Json @{ price=111 }) -ContentType 'application/json'

# delete product 2
curl -Method DELETE -Uri "http://localhost/store_app/api/products.php?id=2"

# test auth
curl -Method POST -Uri http://localhost/store_app/api/auth.php -Body (ConvertTo-Json @{ username='admin'; password='yourpassword' }) -ContentType 'application/json' | ConvertFrom-Json
```

Notes & next steps:

- The current API stores `icon` in the DB `image_url` column. That can be adapted to a proper image storage later.
- I implemented basic validation and normalized the product JSON shape for the frontend.
- Next recommended steps: wire `orders.html`, `invoices.html` and other pages to their corresponding APIs similarly, and add session handling on the server if you want to protect endpoints.

If you want, I can:
- Add server-side session (PHP) to protect API endpoints instead of client-side `localStorage` checks.
- Implement APIs for `orders` and `invoices`.
- Add simple role-based access control middleware.

Tell me which next step you'd like me to implement.