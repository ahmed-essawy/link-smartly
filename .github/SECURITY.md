# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability in Link Smartly, please report it responsibly.

**Do NOT open a public GitHub issue for security vulnerabilities.**

### How to Report

1. **Email**: Send details to **security@minicad.io**
2. **Include**:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

### What to Expect

- **Acknowledgment** within 48 hours of your report
- **Assessment** and initial response within 5 business days
- **Fix timeline** communicated once validated — typically within 14 days for critical issues
- **Credit** in the changelog (unless you prefer to remain anonymous)

### Scope

The following are in scope:

- Cross-site scripting (XSS) in admin or rendered content
- SQL injection
- Cross-site request forgery (CSRF) bypasses
- Privilege escalation
- Unauthorized data access or modification
- Path traversal or file inclusion

### Out of Scope

- Issues requiring physical access to the server
- Issues in WordPress core, themes, or other plugins
- Social engineering attacks
- Denial of service (DoS) attacks

## Security Best Practices

Link Smartly follows WordPress security best practices:

- All form handlers verify nonces via `check_admin_referer()`
- All privileged actions check `current_user_can( 'manage_options' )`
- All input is sanitized (`sanitize_text_field()`, `esc_url_raw()`, `absint()`)
- All output is escaped (`esc_html()`, `esc_attr()`, `esc_url()`)
- DOMDocument is used for HTML parsing — no regex on HTML content
- No `eval()`, `base64_decode()`, or error suppression operators
