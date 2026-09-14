# Step script for the "Post a news item" article (#524, ADR-0025).
#
# Persona: the News Editor of Communications — she may post, so the "New post"
# control and the composer render.
# Run: resources/help/screenshot-runner/run.sh post-a-news-item

persona nadia.haddad@dmv.test
start /news

nav /news 01                # the feed with the "New post" button
act openNewsComposer 02     # the new-post form open: Posting group, Title, Body
