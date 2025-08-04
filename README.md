# User Switcher

**User Switcher** lets privileged users impersonate any other account to debug
permissions or reproduce issues. A floating “Return” button is displayed while
impersonating, allowing a seamless switch back to the original user.

## Installation

```bash
cd web/modules/contrib
# Copy or clone the module into this directory:
cp -R user_switcher /path/to/drupal/web/modules/contrib
drush en user_switcher -y
```

Grant the *“Switch to another user”* permission to the roles that should be able
to impersonate accounts.

## Usage

1. Navigate to **Administration → People → Switch user**.
2. Select a user from the autocomplete field and press **Switch**.
3. While impersonating, use the floating **Return** button at bottom‑right to
   restore your original session.

Security is enforced with CSRF tokens and the module prevents impersonating the
super‑admin (UID 1) unless you are already UID 1.
