<script setup lang="ts">
import DocumentList from '@/components/DocumentList.vue';
import DocumentStats from '@/components/DocumentStats.vue';
import DocumentUpload from '@/components/DocumentUpload.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface DocumentStats {
    total: number;
    processing: number;
    completed: number;
    failed: number;
    totalSize: number;
}

interface Document {
    id: number;
    title: string;
    original_filename: string;
    file_size: number;
    mime_type: string;
    processing_status: string;
    uploaded_at: string;
    tags: string[];
}

interface Props {
    documents: {
        data: Document[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: DocumentStats;
    search?: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: '/documents',
    },
];
</script>

<template>
    <Head title="Documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Documents</h1>
                    <p class="text-muted-foreground">Upload and manage your documents with AI-powered analysis</p>
                </div>
            </div>

            <DocumentStats :stats="props.stats" />

            <DocumentUpload @uploaded="$inertia.reload()" />

            <DocumentList :documents="props.documents" :search="props.search" @refresh="$inertia.reload()" />
        </div>
    </AppLayout>
</template>
