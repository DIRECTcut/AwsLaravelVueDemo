<script setup lang="ts">
import DocumentList from '@/components/DocumentList.vue';
import DocumentStats from '@/components/DocumentStats.vue';
import DocumentUpload from '@/components/DocumentUpload.vue';
import ProcessingStatusIndicator from '@/components/ProcessingStatusIndicator.vue';
import { useDocumentProcessing } from '@/composables/useDocumentProcessing';
import { useAppToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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

// Initialize real-time processing updates
const page = usePage();
const user = computed(() => page.props.auth?.user);
const { processingUpdates, isConnected } = useDocumentProcessing(user.value?.id);
const { showSuccess, showInfo } = useAppToast();

// Handle document upload completion
function handleDocumentUploaded(response: any) {
    console.log('Document uploaded successfully:', response);

    // Show upload success toast
    showSuccess('📄 Document uploaded successfully! Processing will begin shortly...', {
        duration: 4000,
    });

    // Use Inertia's partial reload to update only documents and stats
    // This avoids a full page reload while showing the new document immediately
    // The real-time updates will then show processing progress
    router.reload({ only: ['documents', 'stats'] });
}

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
                <div class="flex items-center gap-2">
                    <div v-if="isConnected" class="flex items-center gap-1 text-sm text-green-600">
                        <div class="h-2 w-2 rounded-full bg-green-500"></div>
                        Live updates connected
                    </div>
                    <div v-else class="flex items-center gap-1 text-sm text-gray-500">
                        <div class="h-2 w-2 rounded-full bg-gray-400"></div>
                        Connecting...
                    </div>
                </div>
            </div>

            <ProcessingStatusIndicator :processing-updates="processingUpdates" />

            <DocumentStats :stats="props.stats" />

            <DocumentUpload @uploaded="handleDocumentUploaded" />

            <DocumentList :documents="props.documents" :search="props.search" @refresh="router.reload()" />
        </div>
    </AppLayout>
</template>
