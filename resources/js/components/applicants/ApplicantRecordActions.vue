<script setup lang="ts">
// The Applicant record's actions (#929, ADR-0038 §8): Print record for every reader, and Delete,
// offered only while no Member is linked and only to whoever may edit (the server sends
// `canDelete`), behind a confirmation. The server re-checks both on the DELETE.
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Link, router } from '@inertiajs/vue3';
import { PhPrinter, PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps<{ applicantId: number; name: string; canDelete: boolean }>();

const deleteOpen = ref(false);

const confirmDelete = () => {
    router.delete(route('applicants.destroy', { applicant: props.applicantId }), {
        onFinish: () => (deleteOpen.value = false),
    });
};
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button variant="outline" as-child>
            <Link :href="route('officer.members.applicants.print-record', { applicant: applicantId })">
                <PhPrinter aria-hidden="true" />
                {{ trans('applicants.outputs.print_record') }}
            </Link>
        </Button>
        <Button v-if="canDelete" type="button" variant="outline" @click="deleteOpen = true">
            <PhTrash aria-hidden="true" />
            {{ trans('applicants.outputs.delete') }}
        </Button>

        <AlertDialog v-if="canDelete" v-model:open="deleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ trans('applicants.outputs.delete_title', { name }) }}</AlertDialogTitle>
                    <AlertDialogDescription>{{ trans('applicants.outputs.delete_body') }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ trans('applicants.outputs.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete">{{ trans('applicants.outputs.delete') }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
