# Sapiens — a one-time download that expires

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Asked by the Sapiens app (`HOME_HABILIS/local/sapiens`). Sapiens generates a sensitive file on demand (an encrypted export of personal health data, packed by `php-file`, brief `sapiens-encrypted-zip.md` there) and hands it over through a link that works **once**, for **10 minutes**; the file is deleted as it is handed over. The code lives in `src/Service/PatientExportService.php` (`take()`, `purgeExpired()`) and in the download route of `DashboardController`. Nothing here knows what the file contains: any application that generates a sensitive document for one user needs it.

## Expected

- **Deposit** a file (a path or a content, a download name, a MIME type, a lifetime) and get back an opaque token: random, at least 192 bits, used as the only key. It never contains a path and is never derived from an id.
- **Take**: the token returns the file once. It is deleted as it is handed over, so a second call answers "not found". An expired token answers "not found" too, the same answer as an unknown one.
- **Bound to its owner (optional):** the deposit may name the user it is for, and taking it as someone else answers "not found".
- A **ready-made response** for the controller: a `BinaryFileResponse` or a stream, `Content-Disposition: attachment` with the download name, `Cache-Control: no-store`, the file deleted after sending.
- **Storage** in a private directory (`var/…`), permissions `0700`, outside `public/`. A **purge** of expired files, run on every deposit and available as a console command for the scheduler.
- **Concurrency:** two requests with the same token hand the file over once at most. Rename the file before reading it, or take a lock.
- The token never appears in logs. Declare a masker to `symfony-security` for the download route.

## Tests

- The first take returns the content, the second answers "not found".
- After the lifetime, "not found", and the file is gone.
- Unknown, expired and already-taken tokens get the same answer.
- With an owner set, another user gets "not found".
- Two concurrent takes: one succeeds, one gets "not found".
