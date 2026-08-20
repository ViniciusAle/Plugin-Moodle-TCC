# local_latracker — Learning Analytics Tracker

Local plugin for Moodle 4.5.x that gives teachers (and site admins) a
dashboard to import CSV files from Google Drive and a Learning Analytics
page with charts built from student navigation/activity tracking data.

## 1. Installing the plugin

1. Copy this `latracker` folder into `<moodle>/local/`, so the plugin
   lives at `<moodle>/local/latracker`.
2. Visit **Site administration > Notifications** (or run
   `php admin/cli/upgrade.php`) to install the plugin and create the
   database tables (`db/install.xml`).
3. Build the AMD JavaScript modules (Moodle ships JS source in `amd/src`
   and expects compiled output in `amd/build`):
   ```bash
   cd <moodle-root>
   npm install          # once, if node_modules doesn't exist yet
   npx grunt amd --root=local/latracker
   ```
4. Purge caches: **Site administration > Development > Purge caches**.

## 2. Creating the Google OAuth Client ID (Google Cloud Console)

1. Go to <https://console.cloud.google.com/> and create (or select) a project.
2. **APIs & Services > Library**: enable the **Google Drive API**.
3. **APIs & Services > OAuth consent screen**:
   - User type: *External* (or *Internal* for a Google Workspace domain).
   - Add the scope `https://www.googleapis.com/auth/drive.readonly`.
   - While testing, add your teacher Google accounts under *Test users*
     (Google restricts sensitive scopes to verified apps or listed test
     users until the app passes verification).
4. **APIs & Services > Credentials > Create credentials > OAuth client ID**:
   - Application type: **Web application**.
   - Authorized redirect URI:
     `https://<your-moodle-domain>/admin/oauth2callback.php`
     (this is Moodle core's fixed OAuth2 callback endpoint — every issuer
     you configure in Moodle uses this same URL).
5. Copy the generated **Client ID** and **Client secret** — you'll paste
   them into Moodle in the next step.

## 3. Registering the OAuth 2 service in Moodle

1. Go to **Site administration > Server > OAuth 2 services**.
2. Click **Create new custom service** and choose the **Google** template
   (Moodle pre-fills the Google endpoints for you).
3. Fill in:
   - **Client ID** / **Client secret**: from step 2.5 above.
   - **Service base URL**: leave the Google default.
4. Open the **Services** tab of the issuer and enable **Google Drive**
   (this grants the `drive.readonly` scope used by this plugin).
5. Save, then make sure the issuer is **Enabled**. You can leave "Show on
   login page" off, since this plugin only uses the issuer for API access,
   not for logging in to Moodle.

## 4. Pointing the plugin at the issuer

1. Go to **Site administration > Plugins > Local plugins > Learning
   Analytics Tracker**.
2. Under **Google OAuth 2 issuer**, select the "Google" service created
   in step 3.
3. Leave **Enable student tracking** checked (it is on by default) unless
   you want to temporarily disable event collection site-wide.

## 5. Using the plugin

- Teachers/admins: open a course, and find **Learning Analytics Tracker**
  and **Learning Analytics Insights** in the course secondary navigation
  (added by `local_latracker_extend_navigation_course()`), or browse
  directly to `/local/latracker/index.php?courseid=<id>`.
- On the dashboard, click **Connect Google Drive**, grant access, then
  pick the CSV files to import and click **Import selected files**.
- Open **Learning Analytics Insights** (`/local/latracker/analytics.php`)
  to see the engagement, task-behaviour and CSV cross-analysis charts.

## 6. Access model

| Capability                              | Who has it by default        |
|------------------------------------------|-------------------------------|
| `local/latracker:view`                   | Editing teacher, Manager (admins always pass) |
| `local/latracker:viewanalytics`          | Editing teacher, Manager      |
| `local/latracker:manageintegrations`     | Editing teacher, Manager      |
| `local/latracker:track`                  | Student, Teacher, Editing teacher, Manager |

All AJAX calls go through Moodle's external function/AJAX dispatcher
(`db/services.php`, `ajax => true`), which requires a valid `sesskey` for
every request — this is what protects the dashboard and the tracker
against CSRF. Each external function additionally re-checks capabilities
server-side with `require_capability()`.

## 7. Extending the analytics page with a new insight

Implement `local_latracker\analytics\insight` and add an instance to the
array returned by `local_latracker\analytics_engine::get_providers()`
(`classes/analytics_engine.php`). Nothing else needs to change — the
Insights page and its template iterate over the registry automatically.
