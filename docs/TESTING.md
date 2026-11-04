# Guest Register: Test Report

**Version tested:** 1.0
**Test date:** 5 November 2026
**Environment:** Windows 11, XAMPP (Apache 2.4.58, PHP 8.0.30, MariaDB 10.4.32), Google Chrome
**Requirements baseline:** Guest_Register_PRD_v1 (approved)

## Summary

**97 of 97 automated tests passed.** They cover every item in PRD §16 (Testing Requirements) and the completion criteria in PRD §15. The tests were run end to end against the real application through HTTP requests. They used four test accounts: an administrator, a receptionist, a security officer, and one deactivated account. The database was checked directly after each action. Responsive layout was checked from screenshots at desktop, tablet and mobile widths.

During final testing, two defects were found and fixed. Both fixes were re-tested (see "Defects found and fixed").

## Method

- **Automated end-to-end tests.** A script logged in as each role, submitted forms (including deliberately invalid ones), and compared the result with the database.
- **Attack and edge cases.** The tests sent requests that bypass the user interface: direct form posts by unauthorised roles, missing security tokens, SQL injection text, HTML in names, double submissions, and two staff issuing the same card at the same moment.
- **Database rules.** The tests tried to save invalid data straight into the database, to confirm the database itself refuses it.
- **Visual checks.** Every screen was screenshotted and reviewed at 1400 px (desktop), 700 px (tablet) and 600 px (mobile) widths.

## 16.1 Authentication and permission testing

| Test | Result |
|---|---|
| Valid login for administrator, receptionist and security officer | Pass |
| Wrong password rejected | Pass |
| Unknown email rejected, with the same message as a wrong password | Pass |
| Deactivated staff account cannot log in | Pass |
| Account deactivated while logged in is signed out on the next page | Pass |
| Login form without its security (CSRF) token is refused | Pass |
| Every protected page redirects to login when logged out (7 pages) | Pass |
| Role permission matrix: 9 pages × 3 roles (table below) | Pass (27/27) |
| Receptionist cannot create staff by posting the form directly | Pass |

**Role permission matrix (as tested)**

| Page | Administrator | Receptionist | Security officer |
|---|---|---|---|
| Dashboard | Yes | Yes | Yes |
| Register visitor | Yes | Yes | No |
| Current visitors / check-out | Yes | Yes | Yes |
| Visit history / view visit | Yes | Yes | Yes |
| Edit visit | Yes | Yes | No |
| Cards: view | Yes | Yes | Yes |
| Cards: add / edit | Yes | No | No |
| Staff management | Yes | No | No |
| Reports | Yes | Yes | No |

## 16.2 Visitor workflow testing

| Test | Result |
|---|---|
| Missing required fields are each identified (name, department, purpose, card) | Pass |
| Invalid phone number format rejected | Pass |
| Valid visitor registered and checked in | Pass |
| Unique reference in the form GR-YYYYMMDD-NNNN | Pass |
| Check-in time is the current Bangladesh time, set by the system | Pass |
| Staff member who registered the visit is recorded | Pass |
| Visitor appears in Current Visitors | Pass |
| Same visitor cannot be checked in twice while inside | Pass |
| Submitting the same form twice creates only one visit | Pass |
| Check-out records a time that is not earlier than check-in | Pass |
| Checked-out visitor leaves Current Visitors | Pass |
| Checking out an already checked-out visit changes nothing | Pass |
| Returning visitor (same name and phone) reuses one visitor record | Pass |
| Returning visitor keeps their company when the field is left blank | Pass |
| Visit history search by name and filter by status | Pass |
| A search with no results explains this and offers to clear filters | Pass |

## 16.3 Card tracking testing

| Test | Result |
|---|---|
| Available card can be issued; the visit shows the card | Pass |
| A card already issued cannot be issued to another visit | Pass |
| Administrator cannot edit a card while it is issued | Pass |
| "Not returned" at check-out requires a note | Pass |
| Unreturned card is flagged Lost | Pass |
| Lost card cannot be reissued | Pass |
| Returned card becomes Available again | Pass |
| Administrator can set a found card back to Available | Pass |
| Duplicate card number rejected, ignoring upper/lower case | Pass |
| Two staff issuing the same card at the same moment: only one succeeds | Pass |

## 16.4 Data integrity and reporting testing

| Test | Result |
|---|---|
| Database refuses a check-out time earlier than check-in | Pass |
| Database refuses an "issued" card that is not linked to a visit | Pass |
| No issued card belongs to a finished visit or no visit | Pass |
| No card record is left open on a finished visit | Pass |
| No visitor record is saved without a visit, including failed attempts | Pass |
| Report totals equal the saved visit records | Pass |
| Dashboard "Inside now" equals the number of active visits | Pass |
| Empty report period shows zero and an explanatory message | Pass |
| Database failure: error page shown (HTTP 500) saying the action was not saved | Pass |
| Database failure: no SQL, table names or file paths shown to the user | Pass |

## 16.5 Responsive testing

| Test | Result |
|---|---|
| Desktop (1400 px): all screens | Pass |
| Tablet (700 px): sidebar becomes a menu button; forms switch to one column | Pass |
| Mobile (600 px): tables scroll sideways inside their panel; buttons stack | Pass |
| Forms, tables, search and navigation at each width | Pass |

## Security checks (PRD §12.1)

| Test | Result |
|---|---|
| Passwords stored only as bcrypt hashes | Pass |
| Session cookie is HttpOnly and SameSite; new session ID after login | Pass |
| Pages cannot be embedded in other sites (X-Frame-Options: DENY) | Pass |
| Private files cannot be opened in a browser (config, SQL files, includes, .git, PRD, settings) | Pass (6/6) |
| HTML typed into a visitor name is displayed as text, not run | Pass |
| SQL injection text in search is treated as plain text | Pass |
| Saved records are still there after logging out and back in | Pass |

## PRD §15 Acceptance criteria

| Criterion | Status |
|---|---|
| Authorized staff can log in and log out | Met |
| Role-based access restrictions work as approved | Met |
| Staff can create a visitor record with all required fields | Met |
| Check-in and check-out timestamps are saved accurately | Met |
| Visitor cards can be issued, returned, and flagged as lost/unavailable | Met |
| One card cannot be assigned to multiple active visits | Met |
| Current Visitors shows active visits accurately | Met |
| Visit history search and filters return correct records | Met |
| Daily and date-range summaries are available | Met |
| Saved records remain available after a new session | Met |
| Invalid input and database failures are handled clearly | Met |
| Core screens work on desktop and mobile browsers | Met (tested in Chrome; see limitations) |
| Critical functional, security and data-integrity tests pass | Met |
| Supervisor-approved data retention and operational rules are documented | **Open: needs the supervisor's decision** |

## Defects found and fixed during final testing

1. **Unexpected errors could show technical details.** XAMPP shows PHP error messages in the browser by default, so a failed database query could have displayed SQL and file paths. A global error handler now logs the details and shows a plain "your action was not saved" page. Re-tested: pass.
2. **A returning visitor's company could be erased.** If a returning visitor was recognised and the Company field was left blank, the saved company was cleared. It is now only updated when a value is entered. Re-tested: pass.

Earlier development rounds also found and fixed defects with their own tests. These included a double check-in race, card-issue races, a check-out race, department bars drawn at the wrong length, and layout issues.

## Known limitations

- **Browsers.** Automated and visual tests used Google Chrome. A short manual check in Microsoft Edge and Firefox, and on a real phone, is recommended before release.
- **Login attempts are not rate-limited.** The PRD does not require it. It is recommended before any deployment outside a local network.
- **Data retention.** There is no automatic deletion or archiving of old visitor records yet. The retention period (PRD §18.2, question 6) needs a supervisor decision.
- **Out of scope for Version 1.0 (PRD §6.2 and §19):** host notifications, pre-registration and QR passes, CSV/PDF export (printing is available), and multi-building support.
