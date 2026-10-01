# Digitalna Prodavnica — E-Commerce Web Aplikacija

## Opis projekta

Puna e-commerce web aplikacija za prodaju digitalnih proizvoda, izrađena u okviru
predmeta (Uvod u razvoj Web aplikacija). Aplikacija pokriva sva tri nivoa zahteva:

- **Nivo 1** — Klijent-server komunikacija, validacija, DOM manipulacija
- **Nivo 2** — Autentifikacija, JWT, uloge, klijentsko skladištenje
- **Nivo 3** — MySQL baza podataka, Prepared Statements, bezbednosne mere

---

## Tehnologije

| Sloj | Tehnologija |
|------|-------------|
| Frontend | HTML5, CSS, JavaScript (vanila, bez frameworka) |
| Backend | PHP 8.x |
| Baza podataka | MySQL (utf8mb4) |
| Autentifikacija | JWT (HMAC-SHA256) |
| Heširanje lozinki | bcrypt (`password_hash` / `password_verify`) |
| SQL bezbednost | PDO Prepared Statements |
| Server | Apache (Laragon) |

---

## Struktura projekta
   ecommerce/
├── .htaccess # Bezbednosni headeri + blokada config/
├── README.md
├── config/
│ ├── database.php # PDO konekcija
│ └── security.php # Same-Origin provera
├── api/
│ ├── .htaccess # Prosleđivanje Authorization headera (Windows fix)
│ ├── auth.php # Register + Login (JWT)
│ ├── products.php # CRUD proizvoda
│ ├── cart.php # Korpa (GET/POST/PUT/DELETE)
│ └── orders.php # Porudžbine (GET/POST)
├── assets/
│ ├── css/
│ │ └── style.css
│ └── js/
│ ├── api.js # Ajax helper + JWT decode
│ └── app.js # XSS escape funkcija
└── views/
├── index.html # Prodavnica (glavna)
├── login.html
├── register.html
├── cart.html
├── orders.html
└── admin.html # Admin dashboard


---

## Baza podataka — šema

| Tabela | Opis |
|--------|------|
| `users` | Korisnici (username, email, heširana lozinka, uloga) |
| `products` | Proizvodi (naziv, opis, cena, zaliha, kategorija) |
| `cart_items` | Stavke korpe (user_id, product_id, količina) |
| `orders` | Porudžbine (user_id, ukupno, status) |
| `order_items` | Stavke porudžbine (order_id, product_id, količina, cena) |

---

## Instalacija i pokretanje

### 1. Hardverski i softverski zahtevi

- Windows 10/11
- Laragon (Apache + PHP + MySQL + HeidiSQL) — [laragon.org](https://laragon.org)
- VS Code (editor)
- Browser (Chrome/Firefox/Brave)

### 2. Kreiranje baze podataka

1. Pokreni Laragon → **Start All**
2. Otvori **HeidiSQL** (Laragon → Database → HeidiSQL)
3. Konekcija: `localhost`, user `root`, password prazan
4. Kreiraj bazu: `ecommerce`, Collation: `utf8mb4_unicode_ci`
5. Otvori **Query** tab (F9) i izvrši SQL šemu:

```sql
USE ecommerce;

CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    description TEXT,
    price       DECIMAL(10,2) NOT NULL,
    stock       INT NOT NULL DEFAULT 0,
    category    VARCHAR(100),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cart_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    product_id  INT NOT NULL,
    quantity    INT NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    total       DECIMAL(10,2) NOT NULL,
    status      ENUM('pending','completed','cancelled') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    product_id  INT NOT NULL,
    quantity    INT NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);   

---

### 3. Postavljanje fajlova

Kopiraj ceo ecommerce folder u:

D:\laragon\www\ecommerce\

(ili C:\laragon\www\ecommerce\ — zavisno od lokacije instalacije Laragona)

---

### 4. Prvo pokretanje

Otvori browser → http://localhost/ecommerce/views/register.html

Registruj korisnika

Za admin ulogu: u HeidiSQL-u promeni role u tabeli users iz user u admin

Prijavi se → http://localhost/ecommerce/views/login.html

FUNKCIONALNOSTI

Korisnik (user)

Registracija i prijava
Pregled proizvoda (grid sa karticama)
Pretraga proizvoda po nazivu/opisu
Dodavanje u korpu
Upravljanje korpom (promena količine, uklanjanje)
Kupovina (kreiranje porudžbine)
Pregled istorije porudžbina

Admin

Sve funkcionalnosti korisnika +
Dodavanje novih proizvoda
Izmena postojećih proizvoda
Brisanje proizvoda

BEZBEDNOSNE MERE

Mera	            Implementacija
SQL Injection	    PDO Prepared Statements u svakom upitu (:placeholder)
Lozinke	            password_hash() (bcrypt) + password_verify()
XSS	                escapeHtml() funkcija pre iscrtavanja korisničkih podataka u DOM
CSRF / Same-Origin	config/security.php → provera Origin headera za POST/PUT/DELETE
JWT	                HMAC-SHA256 potpis, istek (1h), provera na serveru pri svakom zahtevu
HTTP headeri	    X-Content-Type-Options, X-Frame-Options, Referrer-Policy
Blokada config/	    .htaccess → Require all denied za database.php


PROBLEMI I RESENJA TOKOM RAZVOJA

Problem 1: phpMyAdmin ne radi (403 Forbidden)
Opis: Otvorom http://localhost/phpmyadmin dobijena greška "Forbidden — You don't have permission to access this resource."

Rešenje: Korišćen HeidiSQL koji je već ugrađen u Laragon (Laragon → Database → HeidiSQL). Konekcija: localhost, user root, prazan password. Baza i tabele kreirane direktno kroz Query tab (F9).

Problem 2: Blanko stranica umesto PHP izlaza
Opis: Otvorom http://localhost/ecommerce/api/auth.php dobijena potpuno prazna (blanko) stranica bez ijedne greške.

Koraci dijagnostike:

Provera da li Apache/PHP radi (http://localhost → radi)
Provera da li test.php radi (ne radi — blanko)
Provera da li je fajl na pravom putanju (da)
Provera da li je kod ispravan (da)

Uzrok: Fajl nije bio sačuvan u VS Code. Izmene su postojale samo u memoriji editora, a Apache je servirao prazan/falsifikovani fajl.

Rešenje: Ctrl+S za sačuvanje. Preporučeno: uključenje Auto Save (File → Auto Save) da se izmene čuvaju automatski.

Problem 3: 401 Unauthorized na Windows + Apache
Opis: Klikom na "Dodaj proizvod" u admin panelu, stranica se vraćala na Login. verifyToken() uvek vraćao null.

Uzrok: Na Windows + Apache, HTTP_AUTHORIZATION header se ne prosleđuje u $_SERVER array-u (za razliku od Linux-a). Zbog toga $_SERVER['HTTP_AUTHORIZATION'] uvek bude null.

Rešenje: Kreiran .htaccess fajl u api/ folderu:
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]   

Ovo pravilo preusmerava Authorization header u HTTP_AUTHORIZATION server varijablu, čime PHP može da ga pročitа.

API ENDPOINTI:

"Metoda",  "Endpoint",           "Opis",                                 "Autentifikacija"
"POST",    "/api/auth.php",      "Register / Login (action parametar)",  "Ne"
"GET",     "/api/products.php",  "Lista proizvoda (+ ?search=)",         "Ne"
"POST",    "/api/products.php",  "Dodaj proizvod",                       "JWT (admin)"
"PUT",     "/api/products.php",  "Izmeni proizvod",                      "JWT (admin)"
"DELETE",  "/api/products.php",  "Obriši proizvod",                      "JWT (admin)"
"GET",     "/api/cart.php",      "Prikazi korpu",                        "JWT"
"POST",    "/api/cart.php",      "Dodaj u korpu",                        "JWT"
"PUT",     "/api/cart.php",      "Izmeni količinu",                      "JWT"
"DELETE",  "/api/cart.php",      "Ukloni iz korpe",                      "JWT"
"GET",     "/api/orders.php",    "Moje porudžbine",                      "JWT"
"POST",    "/api/orders.php",    "Napravi porudžbinu",                   "JWT"

JWT struktura
Header:  { "alg": "HS256", "typ": "JWT" }
Payload: { "id": 1, "username": "admin", "role": "admin", "exp": 1727760000 }
Secret:  'moj_tajni_kljuc_2024'   

Važi 1 sat (3600 sekundi)
Potpis: HMAC-SHA256
Verifikacija na serveru pri svakom zahtevu (provera potpisa + isteka)
