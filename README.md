# Jolly Giraffes

**Jolly Giraffes** is a custom childcare management and parent communication application built with PHP, MySQL/MariaDB, JavaScript, HTML, and CSS.

The application was developed specifically for childcare/daycare operations, with a focus on making daily classroom tasks fast and simple for staff while providing parents with useful information about their child's day.

## Features

### Child Check-In / Check-Out

Jolly Giraffes provides a kiosk-style check-in and check-out interface designed for use at the childcare facility.

Features include:

* Child check-in and check-out
* Employee time clock functionality
* Kiosk mode
* Administrative kiosk unlock using a PIN
* Kiosk lock functionality
* Child avatars/photos
* Parent/staff-friendly touchscreen interface
* Daily attendance tracking

The kiosk can be locked so that an administrator must unlock it before the check-in screen can be used. Once unlocked, the session remains active until the kiosk is explicitly locked again.

### Daily Status

The Daily Status system allows staff to record information about each child's day and provides a parent-facing view of that information.

Status information includes:

* Mood
* Meals
* Meal ratings
* Activities
* Potty/diaper events
* Bottles
* Naps
* Incidents
* Notes
* Photos and other attachments
* Parent requests

The system is designed around quick-entry controls so staff can record common events without having to complete lengthy forms.

### Mood Tracking

Staff can record a child's mood using predefined options:

* Happy
* Sad
* Angry
* Tired
* Energetic
* Calm
* Silly
* Not Well

Each mood is associated with an emoji and display styling.

### Potty / Diaper Tracking

Potty Time supports timestamped entries for:

* Wet diaper
* Dirty diaper
* Used potty
* Accident

Depending on the entry type, staff can also record additional information such as:

* Diaper cream used
* Pee
* Poop

Entries may have attachments associated with them.

### Meals

The Daily Status system supports three daily meal slots:

* Breakfast
* Lunch
* Dinner

Staff can enter menu information for each meal and record how the child ate:

* Ate Well
* Ate OK
* Not Hungry

Meal information is stored per child and per day.

### Activities

Staff can record multiple activities for a child during the day.

Current activity types include:

* Belly Time
* Art
* Books
* Free Play
* Indoor Playground
* Outdoor Playground
* Learning
* Pretend Play
* Sensory
* Videos

Activities are stored independently so multiple activities can be recorded for the same child on the same day.

### Bottle Tracking

For younger children, the system provides quick bottle tracking.

Bottle entries include:

* Timestamp
* Amount
* 1–8 ounce quick-select amounts

Bottle tracking is automatically limited based on the configured age threshold.

### Nap Tracking

Nap tracking provides quick duration-based recording.

Available durations include:

* 30 minutes
* 60 minutes
* 90 minutes
* 120 minutes

For older children who do not use individual nap clock-in/clock-out tracking, staff can instead record a simple nap rating:

* Slept Well
* Slept OK
* Restless

### Incident / Injury Quick Reports

The Daily Status interface includes one-tap incident reporting for common classroom incidents.

Current quick-report options include:

* Hurt Someone
* Bit Someone
* Bitten
* Boo Boo
* Band-Aid
* Sick

Incident entries can contain additional notes and attachments. The system distinguishes between behavior, injury, and medical-related incidents.

### Photos and Attachments

Photos and documents can be associated with Daily Status entries.

The application distinguishes common image formats from other document types so that, for example, an uploaded PDF does not generate a "New Photo" notification.

Files are served through an authenticated file gateway rather than being directly exposed as publicly accessible files.

### Parent Status Links

Families can receive a unique parent-facing status link.

The link provides access to their children's daily information without requiring the parent to use the administrative interface.

Family links can be disabled when an account has no enrolled children.

### Notifications

The application includes a notification system supporting browser push notifications.

Push subscriptions are associated with individual family accounts and can be added or removed on a per-device basis.

The application also maintains site-level notification configuration, including the site's VAPID key pair used for Web Push.

### Parent Requests

Staff can record common requests for parents, including:

* Need Diapers
* Clothing Change

Requests can be configured as either single-day notifications or persistent notifications that remain active until cleared.

---

## Technology

Jolly Giraffes is a traditional server-side PHP application.

### Backend

* PHP
* MySQL / MariaDB
* Custom database abstraction layer
* Custom application libraries
* Server-side HTML generation
* AJAX endpoints for interactive operations

### Frontend

* HTML
* CSS
* JavaScript
* jQuery
* jQuery UI
* Responsive/mobile-oriented interface

### Browser Features

The application makes use of modern browser capabilities where appropriate, including:

* AJAX
* Browser notifications
* Web Push
* File uploads
* Touch-friendly controls

---

## Application Structure

The repository is organized around the application's major functional areas.

```text
jollygiraffes/
├── ajax/            AJAX endpoints
├── css/             Stylesheets
├── images/          Images and application graphics
├── lib/             Core application libraries
├── min/             Minified frontend resources
├── notifications/   Notification and Web Push functionality
├── scripts/         Client-side JavaScript
├── templates/       Reusable templates
├── tests/            Application tests
├── config.php       Local application configuration
├── config-sample.php Configuration template
├── files.php        Authenticated file delivery
├── index.php        Main kiosk/application entry point
├── status.php       Daily Status interface
└── ...
```

The application currently contains a substantial amount of functionality in the `lib/` and `notifications/` directories, with Daily Status functionality centered around `lib/status_lib.php`.

---

## Requirements

A typical installation requires:

* PHP
* MySQL or MariaDB
* A web server such as Apache or Nginx
* PHP database support for MySQL/MariaDB
* A writable directory for protected user files
* A modern web browser

The exact PHP/database versions should be determined from the deployment environment and tested before upgrading an existing installation.

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/Syxton/jollygiraffes.git
cd jollygiraffes
```

### 2. Create the Database

Create a MySQL or MariaDB database for the application.

For example:

```sql
CREATE DATABASE jollygiraffes
    CHARACTER SET utf8
    COLLATE utf8_unicode_ci;
```

Create a database user with the appropriate permissions and record the credentials.

### 3. Configure the Application

Copy the sample configuration:

```bash
cp config-sample.php config.php
```

Edit `config.php` and configure:

* Site name
* Site email address
* Street address
* Logo
* Database type
* Database host
* Database name
* Database username
* Database password
* Application directory
* Web root
* Protected file storage location
* Time zone
* Optional Google Analytics ID

Example database configuration:

```php
$CFG->dbtype = 'mysqli';
$CFG->dbhost = 'localhost';
$CFG->dbname = 'jollygiraffes';
$CFG->dbuser = 'username';
$CFG->dbpass = 'password';
```

### 4. Configure Protected File Storage

User-uploaded files must be stored outside the web-accessible directory.

Configure:

```php
$CFG->userfilespath = '/path/to/status_files';
```

**Do not place this directory inside the application's document root.**

The application performs safety checks to prevent the configured file directory from being located inside the application's web-accessible directory or the server's reported document root.

The application also provides `files.php` as the authenticated gateway for retrieving protected files.

### 5. Configure the Web Server

Configure the web server so that the application directory is accessible through the desired URL.

The application uses:

```php
$CFG->wwwroot
```

to build application URLs.

If the application is installed in a subdirectory, configure:

```php
$CFG->directory
```

appropriately.

### 6. Open the Application

Open the configured application URL in a browser.

On startup, the application checks whether the database is installed and performs required database migrations.

The Daily Status subsystem is designed so that its schema migrations can safely be run repeatedly. Existing installations are upgraded as new Daily Status functionality is introduced.

---

## Database Migrations

The application contains its own lightweight migration mechanism.

The Daily Status library checks for required database columns, indexes, and tables and creates or upgrades them as necessary.

Among the Daily Status database structures are:

```text
events
notes
documents
accounts
status_menu
status_activity
status_nap_rating
```

Daily Status extends existing application tables when necessary rather than requiring a separate database application.

For example, the Daily Status migration system adds fields used for:

* Child identification
* Account identification
* Day keys
* Event timestamps
* Potty information
* Incident information
* Attachments
* Family status links

The migration process is designed to be safe to execute on every request and only makes changes when the required schema is not already present.

---

## Security Considerations

Jolly Giraffes handles sensitive childcare information, so deployment should be treated as a production application rather than a simple public PHP website.

### Protected Files

Uploaded files should be stored outside the web root.

The application explicitly checks the configured file path and refuses to run when the path has not been configured correctly.

### Authentication

Administrative functionality is protected by application authentication and PIN-based kiosk controls where appropriate.

### Parent Links

Parent-facing Daily Status links should be treated as private access credentials.

Do not publish family status URLs publicly.

### Database Credentials

Never commit a production `config.php` containing database credentials to a public repository.

Use `config-sample.php` as the template for local configuration.

### HTTPS

Production deployments should use HTTPS, particularly because the application handles:

* Child information
* Parent information
* Attendance information
* Photos
* Notes
* Authentication credentials
* Web Push subscriptions

---

## Development

The repository is intended to be developed as a complete application rather than as a framework package.

When making changes:

1. Test the change in a development environment.
2. Verify database migrations against a copy of the production schema.
3. Test both administrative and parent-facing interfaces.
4. Test touchscreen/mobile interfaces when changing kiosk functionality.
5. Verify that uploaded files remain outside the web root.
6. Check browser console errors for JavaScript/AJAX changes.
7. Test notification behavior when modifying Web Push functionality.

---

## Daily Status Data Model

Daily Status generally uses a combination of:

```text
Child
  │
  ├── Mood/Event
  ├── Potty/Event
  ├── Bottle/Event
  ├── Incident/Event
  ├── Meal
  ├── Activity
  ├── Nap
  ├── Notes
  └── Attachments
```

Most timestamped events are associated with a child and a day, allowing the application to present a chronological record of the child's day while still supporting summary information such as meals and activities.

---

## Web Push

The application supports browser Web Push notifications.

A device subscription contains the information necessary to communicate with the browser and is associated with a family account.

Subscriptions are identified using a SHA-256 hash of the push endpoint.

The notification subsystem also maintains the site's VAPID credentials used for push authentication.

---

## Configuration

The primary configuration file is:

```text
config.php
```

A starting template is provided as:

```text
config-sample.php
```

Important configuration values include:

| Setting         | Purpose                                 |
| --------------- | --------------------------------------- |
| `sitename`      | Name displayed by the application       |
| `siteemail`     | Application email address               |
| `streetaddress` | Facility address                        |
| `fein`          | Facility tax identification information |
| `logo`          | Application logo                        |
| `dbtype`        | Database driver                         |
| `dbhost`        | Database server                         |
| `dbname`        | Database name                           |
| `dbuser`        | Database username                       |
| `dbpass`        | Database password                       |
| `directory`     | Application subdirectory                |
| `wwwroot`       | Application URL                         |
| `userfilespath` | Protected file storage directory        |
| `fileserveurl`  | Authenticated file delivery endpoint    |
| `analytics`     | Optional Google Analytics identifier    |
| `timezone`      | Application timezone                    |
| `servertz`      | Server timezone                         |

---

## Project Philosophy

Jolly Giraffes is designed around a simple principle:

> **Common childcare tasks should take as few taps as possible.**

The application favors quick actions and purpose-built workflows over complicated forms. Staff should be able to record a mood, meal, diaper change, bottle, activity, incident, photo, or nap with minimal interruption to classroom activities.

At the same time, the information entered by staff should be organized into a useful daily record that parents can easily understand.

---

## License

No open-source license is currently specified in the repository.

Unless a license is added to the project, the source code should be treated as **all rights reserved**.

---

## Author

**Matthew Davidson (Syxton)**

The repository is maintained by the original developer and was built as a custom childcare management application.

---

## Repository

https://github.com/Syxton/jollygiraffes
