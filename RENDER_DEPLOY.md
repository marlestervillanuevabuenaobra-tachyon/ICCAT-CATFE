# Deploying ICCAT CATFÉ on Render (PostgreSQL)

Render has no MySQL, so this copy of the project uses **PostgreSQL** (Render's database)
and runs the PHP site in a **Docker** web service.

## 1. Put the project on GitHub
1. Create a new GitHub repository (e.g. `catfe`).
2. Upload everything in this folder (the `Dockerfile` must be at the repo root).

## 2. Create the database on Render
1. Render dashboard → **New +** → **PostgreSQL**.
2. Name: `catfe-db` · Database: `catfe_db` · Region: pick the one closest to you (e.g. Singapore) · Instance type: **Free**.
3. Click **Create Database**, wait until it says **Available**.
4. On the database page, copy:
   - **Internal Database URL** (for the web service), and
   - **External Database URL** (for importing the tables from your PC).

## 3. Import the tables and menu
Use the **External Database URL**.

**Option A – psql (command line)**
```
psql "PASTE_EXTERNAL_DATABASE_URL" -f database/catfe_postgres.sql
```
**Option B – DBeaver / pgAdmin (GUI)**
New PostgreSQL connection using the External URL values → open `database/catfe_postgres.sql` → run the whole script.

Check: `SELECT COUNT(*) FROM products;` should return 20.

## 4. Create the web service
1. **New +** → **Web Service** → connect your GitHub repo.
2. Language/Runtime: **Docker** (Render finds the `Dockerfile` automatically). Same region as the database. Instance type: **Free**.
3. Open **Environment** and add:
   | Key | Value |
   |---|---|
   | `DATABASE_URL` | the **Internal Database URL** |
   | `ADMIN_PASSWORD` | your own admin password (replaces the hard-coded one) |
4. **Create Web Service**. First build takes a few minutes.
5. Open the `https://<name>.onrender.com` link → loading screen → login page.

(Shortcut: **New + → Blueprint** with this repo uses `render.yaml` and creates both for you.)

## 5. Test
Sign up → add items → apply `NEWCAT20` → checkout → open the admin login → update an order status.

## Things to know (free tier)
- The database **expires 30 days after creation** (14-day grace period, then it is deleted). Back up with
  `pg_dump "EXTERNAL_URL" > backup.sql`, or upgrade to a paid database.
- The web service **sleeps after 15 min** without visitors; the first visit afterwards takes ~1 min.
- Only one free Postgres database per Render account.
- Sessions/cart are stored on the container's disk, so a restart or redeploy logs people out.
- Optional: set `GOOGLE_SHEET_WEBHOOK_URL` in `includes/functions.php` as before.

## Troubleshooting
- **"DATABASE_URL is not set"** → add it under Environment, then redeploy.
- **"Connection failed … could not translate host name"** → you used the wrong URL type, or the service and database are in different regions (use the Internal URL only within the same region; otherwise use the External URL and set `DB_SSLMODE=require`).
- **"relation ... does not exist"** → step 3 wasn't run against this database.
