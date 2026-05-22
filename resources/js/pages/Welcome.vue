<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
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
                    A rebuild of the volunteer site for the Department of Museum Volunteers at the Royal Ontario Museum is in progress. For now,
                    please continue to use the live site.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <Button as-child>
                    <a href="https://dmv-rom.ca" target="_blank" rel="noopener">Go to dmv-rom.ca</a>
                </Button>
                <Button v-if="page.props.auth.user" variant="outline" as-child>
                    <Link :href="route('dashboard')">Go to dashboard</Link>
                </Button>
                <Collapsible v-else>
                    <CollapsibleTrigger as-child>
                        <Button variant="ghost" class="w-full">Volunteer sign-in</Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent class="flex flex-wrap gap-3 pt-3">
                        <Button variant="outline" as-child>
                            <Link :href="route('login')">Log in</Link>
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link :href="route('register')">Register</Link>
                        </Button>
                    </CollapsibleContent>
                </Collapsible>
            </CardContent>
        </Card>
    </div>
</template>
