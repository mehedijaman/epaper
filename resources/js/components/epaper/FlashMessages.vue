<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const page = usePage<{
    flash: {
        success?: string;
        error?: string;
        warnings: string[];
    };
}>();

const flash = computed(() => page.props.flash);
</script>

<template>
    <div class="space-y-3" v-if="flash.success || flash.error || flash.warnings.length">
        <Alert v-if="flash.success">
            <AlertTitle>Success</AlertTitle>
            <AlertDescription>{{ flash.success }}</AlertDescription>
        </Alert>

        <Alert v-if="flash.error" class="border-destructive/50 text-destructive">
            <AlertTitle>Error</AlertTitle>
            <AlertDescription>{{ flash.error }}</AlertDescription>
        </Alert>

        <Alert v-if="flash.warnings.length" class="border-amber-500/40 text-amber-800">
            <AlertTitle>Image warnings</AlertTitle>
            <AlertDescription>
                <ul class="list-disc pl-4">
                    <li v-for="warning in flash.warnings" :key="warning">{{ warning }}</li>
                </ul>
            </AlertDescription>
        </Alert>
    </div>
</template>
