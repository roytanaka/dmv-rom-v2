<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
</script>

<template>
    <Head title="DMV-ROM" />
    <div class="bg-background text-foreground flex min-h-screen items-center justify-center p-6">
        <Card class="w-full max-w-xl">
            <CardHeader>
                <CardTitle>DMV-ROM</CardTitle>
                <CardDescription>
                    Volunteer management for the Department of Museum Volunteers, Royal Ontario Museum. Structural rebuild scaffold.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-3">
                <Dialog>
                    <DialogTrigger as-child>
                        <Button>Smoke-test the toolchain</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Toolchain wired</DialogTitle>
                            <DialogDescription>
                                Inertia, Vue 3, Tailwind, and shadcn-vue are rendering through Laravel. You're looking at a Card and a Dialog — that's
                                three baseline components on screen.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="secondary" as-child>
                                <a href="https://github.com/roytanaka/dmv-rom-v2" target="_blank" rel="noopener">Repo</a>
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
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
