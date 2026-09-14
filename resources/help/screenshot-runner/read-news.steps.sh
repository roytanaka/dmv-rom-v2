# Step script for the "Read news" article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — an ordinary reader of the feed.
# Run: resources/help/screenshot-runner/run.sh read-news

persona amara.abara@dmv.test
start /news

nav /news 01                # the feed, newest item first
