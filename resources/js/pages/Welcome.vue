<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
</script>

<template>
    <Head title="DMV-ROM" />
    <div class="flex min-h-screen items-center justify-center bg-background p-6 text-foreground">
        <Card class="w-full max-w-xl">
            <CardHeader>
                <CardTitle>Welcome, DMV Volunteers</CardTitle>
                <CardDescription>
                    This is the in-progress rebuild of the volunteer system for the Department of Museum Volunteers at the Royal Ontario Museum. The
                    current live system is still at dmv-rom.ca — head there to sign in to scheduling, hours, and the volunteer directory.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-3">
                <Button as-child>
                    <a href="https://dmv-rom.ca" target="_blank" rel="noopener noreferrer">Go to dmv-rom.ca</a>
                </Button>
                <Button v-if="page.props.auth.user" variant="outline" as-child>
                    <Link :href="route('dashboard')">Go to dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="outline" as-child>
                        <Link :href="route('login')">Log in</Link>
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="route('register')">Register</Link>
                    </Button>
                </template>
            </CardContent>
        </Card>
    </div>
</template>
