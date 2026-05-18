<?php

test('intentional ci break — branch-protection verification for main, do not merge', function () {
    expect(true)->toBeFalse();
});
