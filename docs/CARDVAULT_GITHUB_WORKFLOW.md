# CardVault - GitHub Development Workflow

This document outlines the professional branching, commit, pull request, and deployment standards adopted for **CardVault**. To maintain high repository quality, we enforce strict local validations before merging or pushing code.

---

## 1. Branching Strategy

We follow a structured branching hierarchy to isolate features under active development from stable production-ready code.

```
       [feature/follow-up-center] ---- (Local QA) ---- [PR Review]
      /                                                          \
[main] ----------------------------------------------------------- [Merge]
      \                                                          /
       [fix/ocr-parser] -------------- (Local QA) ---- [PR Review]
```

### Permanent Branches
*   **`main`**: Production-ready, fully verified stable branch. Direct pushes to `main` are strictly forbidden unless resolving a hotfix.

### Development Branches
*   **Feature Branches (`feature/<feature-name>`)**: Created for new product capabilities.
    *   *Examples*: `feature/follow-up-center`, `feature/digital-business-card`, `feature/lead-pipeline`.
*   **Bug Fix Branches (`fix/<bug-name>`)**: Created for targeting specific regressions or interface layout fixes.
    *   *Examples*: `fix/ocr-heuristics`, `fix/mobile-responsive-scanner`.

---

## 2. Commit Message Standards

We enforce descriptive, semantic commit prefixes. Commit messages should state what was changed and why. Do not use generic messages (e.g. `update`, `fix`, `changes`).

### Formats & Prefixes
*   **`feat: <description>`**: Introducing a new functional feature.
    *   *Example*: `feat: add WhatsApp follow-up template composer`
*   **`fix: <description>`**: Repairing a bug or fixing layout issues.
    *   *Example*: `fix: prevent duplicate note logging on double-click`
*   **`test: <description>`**: Adding automated QA test coverage or helper scripts.
    *   *Example*: `test: introduce isolated E2E database verification script`
*   **`docs: <description>`**: Modifying technical or customer documentation.
    *   *Example*: `docs: document digital card saving vCard layout`
*   **`style: <description>`**: Visual enhancements, code formatting, linting changes.
    *   *Example*: `style: align CRM table row margins on dashboard`

---

## 3. Pull Request Guidelines

Before any development branch is merged into `main`, a Pull Request (PR) must be submitted and pass local validation checks.

### Pull Request Checklists
1.  **Code Syntax Validation**: Verify all modified scripts compile without warnings:
    ```powershell
    php -l <modified_file_path>
    ```
2.  **User Isolation**: Double check that all database select queries combine target resource IDs and `$userId` to ensure data remains strictly segmented.
3.  **CSRF Protections**: Confirm `validate_csrf()` is called on all POST/DELETE request routes.
4.  **No Fabricated History**: Commits must reflect actual development trajectory. Do not rewrite history or fabricate commit timestamps.

### PR Description Template
```markdown
## Summary
[Description of the new feature/bugfix and rationale]

## Files Changed
* [NEW/MODIFIED] [file name](file:///path/to/file)

## Database Changes
[Details of any SQL queries or tables added/altered]

## Security Auditing
* CSRF validation checked: [Yes/No]
* User segmentation enforced: [Yes/No]

## Local Testing
[Details of local E2E testing executed and results]
```

---

## 4. Secure cPanel Deployments

*   **Production Configurations**: The file `config/database.php` in production holds sensitive keys and passwords. It must remain excluded from Git (`.gitignore`) at all times.
*   **GitHub Actions Secrets**: Deployment secrets (`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`) must be stored within GitHub Repository Secrets rather than hardcoded in workflow YAML files.
