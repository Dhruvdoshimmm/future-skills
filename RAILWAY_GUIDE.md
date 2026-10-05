# Go live on Railway (website + MySQL database) - step by step

You need: a GitHub account, a Railway account (railway.com, sign in with GitHub), Git installed on your PC.

## Part 1 - Put the project on GitHub
1. Create a **new empty repository** on github.com (name: `future-skills`, Private is fine, do NOT add a README).
2. Unzip the project, open a terminal (Command Prompt / PowerShell) **inside the `future-skills` folder** and run:
   ```
   git init
   git add .
   git commit -m "Future Skills website"
   git branch -M main
   git remote add origin https://github.com/YOUR-USERNAME/future-skills.git
   git push -u origin main
   ```
3. `includes/config.local.php` (your XAMPP settings) is ignored by `.gitignore`, so it is not uploaded. Good.

## Part 2 - Create the Railway project and the database
1. Railway dashboard -> **New Project** -> **Deploy from GitHub repo** -> allow access -> choose `future-skills`.
   Railway finds the `Dockerfile` and starts building. (The first build may show errors about the database - normal until step 3.)
2. In the project canvas click **+ Create** (or **New**) -> **Database** -> **Add MySQL**. Wait until it shows as active.
3. Click your **website service** (the GitHub one) -> **Variables** -> add:

   | Name | Value |
   |---|---|
   | `MYSQL_URL` | `${{MySQL.MYSQL_URL}}`  (type it exactly; `MySQL` = the database service name) |
   | `PORT` | `80` |
   | `ADMIN_PASSWORD` | a strong password you choose (this is your admin login) |
   | `OTP_MODE` | `sms` |
   | `TWILIO_SID` | from Twilio (see Part 4) |
   | `TWILIO_TOKEN` | from Twilio |
   | `TWILIO_FROM` | your Twilio phone number, e.g. `+1415...` |

   Railway redeploys automatically when variables change.
4. Website service -> **Settings** -> **Networking** -> **Generate Domain**. If it asks for a port, enter `80`.
   You get a link like `https://future-skills-production.up.railway.app`.

## Part 3 - Check that it works
1. Open the link. The home page should load (the database tables and default text are created automatically on the first visit - **you do not import any SQL file on Railway**).
2. Open `/admin.php` -> user `admin`, password = your `ADMIN_PASSWORD`.
3. Submit a test message on **Contact Us**, then look at it in Admin -> Inquiries.
4. See the data: Railway -> **MySQL service -> Data** tab -> tables `inquiries`, `inquiry_replies`, `users`, `chat_messages`, `site_content`.

## Part 4 - Phone login (SMS code) with Twilio
1. Create an account at twilio.com and get a phone number that can send SMS.
2. Console home shows **Account SID** and **Auth Token** -> put them in the Railway variables above, with your Twilio number in `TWILIO_FROM`.
3. Trial accounts only send to numbers you have verified in Twilio; upgrade the account for real visitors.
   Sending SMS to Indian numbers may require registered (DLT) templates; if Twilio does not work for you, use an Indian SMS provider and replace `send_sms()` in `includes/auth.php`.
4. Until SMS works, login shows "SMS login is not configured" - the rest of the site (pages, Contact form, Admin) still works.

## Part 5 - Updating the website later
Change files -> `git add .` -> `git commit -m "update"` -> `git push`. Railway rebuilds automatically.
Your data (messages, chats, users, page text) lives in the MySQL service, so redeploys never erase it.
Page text can also be edited live in Admin -> Content Management.

## Optional - your own domain
Website service -> Settings -> Networking -> **Custom Domain** -> enter your domain -> add the CNAME record Railway shows at your domain registrar.

## Troubleshooting
| Problem | Fix |
|---|---|
| Site shows "Application failed to respond" / 502 | Variables: `PORT` = `80`; Networking domain target port = `80`; redeploy. |
| "The database is not reachable" | `MYSQL_URL` missing/typo, or the database service has a different name than `MySQL` in `${{MySQL.MYSQL_URL}}`. Check the MySQL service is running. |
| "Admin login is disabled until ADMIN_PASSWORD..." | Add the `ADMIN_PASSWORD` variable and redeploy. |
| "SMS login is not configured" | Set `OTP_MODE=sms` and the three `TWILIO_*` variables. |
| Build fails | Open the service's **Deployments -> View logs** and read the last lines; make sure `Dockerfile` is in the repo root. |
| Admin password forgotten | Change `ADMIN_PASSWORD` in Variables; it applies after the redeploy. |
