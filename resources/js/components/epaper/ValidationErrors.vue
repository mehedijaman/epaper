<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type InertiaErrors = Record<string, string | string[] | null | undefined>;

const page = usePage<{ errors?: InertiaErrors }>();

const messages = computed(() => {
    const errors = page.props.errors ?? {};

    return Object.entries(errors).flatMap(([field, rawValue]) => {
        if (rawValue === null || rawValue === undefined) {
            return [];
        }

        const values = Array.isArray(rawValue) ? rawValue : [rawValue];

        return values
            .filter((value): value is string => typeof value === 'string' && value.trim() !== '')
            .map((message, index) => ({
                key: `${field}-${index}-${message}`,
                message,
            }));
    });
});
</script>

<template>
    <Alert v-if="messages.length > 0" class="border-destructive/50 text-destructive">
        <AlertTitle>Validation errors</AlertTitle>
        <AlertDescription>
            <ul class="list-disc space-y-1 pl-4 text-sm">
                <li v-for="item in messages" :key="item.key">{{ item.message }}</li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
