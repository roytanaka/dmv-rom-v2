# Read the Mail status page

Check that the app is sending mail. You need the super-tier grant.

## Open the page

1. Select your avatar at the right of the top bar.

    ![The avatar menu: Mail status between Renew Membership and Log out](01.png)

2. Select **Mail status**.

    ![The Mail status page: the four values, with no warning](02.png)

The page shows the mail queue and the task that empties it.

## What the page shows

- **Scheduler last ran** is when the app last checked the queue. It should be within the last minute.
- **Mail last sent** is when the app last sent an email.
- **Pending in queue** is how many emails are waiting to go out.
- **Last connection error** is the last time the app could not reach the mail host.

## The two warnings

- **Cron looks dead** means the scheduler has not run for over ten minutes. No mail is going out.
- **Cannot send** means the last connection to the mail host failed after the last good send.

> **Note:** A large Broadcast leaves a high pending count for up to an hour. That is normal.

## What next

This page is the first place to look when a Member says an email never arrived.
