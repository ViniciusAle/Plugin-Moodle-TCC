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

## 2. Creating the Google credentials (Google Cloud Console)

The CSV picker runs entirely in the browser (Google Identity Services +
the Google Picker widget). The Moodle **server** never talks to Google
directly — only the teacher's browser does, using its own internet
connection. This means:
- The Moodle server does **not** need outbound network access to
  `googleapis.com` (this is what caused the "Carregando arquivos..."
  spinner to hang forever in an earlier version of this plugin, when
  file listing was implemented as a server-to-server call).
- There is no server-side OAuth2 redirect/callback involved, so there is
  nothing to register under *Site administration > Server > OAuth 2
  services* — you only need two values from Google Cloud Console, pasted
  directly into this plugin's own settings page.

Steps:

1. Go to <https://console.cloud.google.com/> and create (or select) a project.
2. **APIs & Services > Library**: enable the **Google Drive API** and the
   **Google Picker API**.
3. **APIs & Services > OAuth consent screen**:
   - User type: *External* (or *Internal* for a Google Workspace domain).
   - Add the scope `https://www.googleapis.com/auth/drive.readonly`.
   - While testing, add your teacher Google accounts under *Test users*
     (Google restricts sensitive scopes to verified apps or listed test
     users until the app passes verification).
4. **APIs & Services > Credentials > Create credentials > OAuth client ID**:
   - Application type: **Web application**.
   - **Authorized JavaScript origins**: add
     `https://<your-moodle-domain>` (no path, no trailing slash). This is
     the only thing Google needs to know about your site — there is no
     redirect URI to configure, since the picker never leaves the page.
   - Copy the generated **Client ID** (the secret is not used by this flow).
5. **APIs & Services > Credentials > Create credentials > API key**:
   - Restrict it: *Application restrictions* → *Websites* →
     `https://<your-moodle-domain>/*`; *API restrictions* → restrict to
     the Google Picker API (and Drive API).
   - Copy the generated **API key**.

## 3. Configuring the plugin

1. Go to **Site administration > Plugins > Local plugins > Learning
   Analytics Tracker**.
2. Paste the **Client ID** and the **Picker API key** from step 2 above.
3. Leave **Enable student tracking** checked (it is on by default) unless
   you want to temporarily disable event collection site-wide.

## 4. Using the plugin

- Teachers/admins: open a course, and find **Learning Analytics Tracker**
  and **Learning Analytics Insights** in the course secondary navigation
  (added by `local_latracker_extend_navigation_course()`), or browse
  directly to `/local/latracker/index.php?courseid=<id>`.
- On the dashboard, click **Selecionar arquivos CSV do Google Drive** — a
  Google popup asks the teacher to sign in and grant read-only Drive
  access, then Google's own file picker opens for choosing one or more
  CSV files. The browser downloads their content directly from Google
  and posts it to the plugin for parsing.
- Open **Learning Analytics Insights** (`/local/latracker/analytics.php`)
  to see the engagement, task-behaviour and CSV cross-analysis charts.

If the popup is blocked by the browser, allow popups for the Moodle
domain and try again — it must be opened directly from the click
handler, which this plugin does.

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
