<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/vue3';
import { CheckCircle, Clock, Download, Eye, FileText, Image, Loader2, MoreVertical, Search, Trash2, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    search?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    refresh: [];
}>();

const searchQuery = ref(props.search || '');
const isLoading = ref(false);

const hasDocuments = computed(() => props.documents.data.length > 0);

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getFileIcon(mimeType: string) {
    if (mimeType.startsWith('image/')) return Image;
    return FileText;
}

function getStatusConfig(status: string) {
    const configs = {
        pending: {
            label: 'Pending',
            variant: 'outline' as const,
            icon: Clock,
            class: 'text-yellow-600',
        },
        processing: {
            label: 'Processing',
            variant: 'secondary' as const,
            icon: Loader2,
            class: 'text-blue-600',
        },
        completed: {
            label: 'Completed',
            variant: 'default' as const,
            icon: CheckCircle,
            class: 'text-green-600',
        },
        failed: {
            label: 'Failed',
            variant: 'destructive' as const,
            icon: XCircle,
            class: 'text-red-600',
        },
    };

    return configs[status as keyof typeof configs] || configs.pending;
}

async function handleSearch() {
    isLoading.value = true;
    try {
        router.get('/documents', { search: searchQuery.value });
    } finally {
        isLoading.value = false;
    }
}

async function downloadDocument(document: Document) {
    try {
        const response = await fetch(`/documents/${document.id}/download`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        if (response.ok) {
            const data = await response.json();
            window.open(data.download_url, '_blank');
        } else {
            throw new Error('Download failed');
        }
    } catch (error) {
        console.error('Error downloading document:', error);
        // TODO: Show error notification
    }
}

async function deleteDocument(document: Document) {
    if (!confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch(`/documents/${document.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        if (response.ok) {
            emit('refresh');
        } else {
            throw new Error('Delete failed');
        }
    } catch (error) {
        console.error('Error deleting document:', error);
        // TODO: Show error notification
    }
}

function viewDocument(document: Document) {
    router.visit(`/documents/${document.id}`);
}

function changePage(page: number) {
    router.get('/documents', {
        page,
        search: searchQuery.value,
    });
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Documents</CardTitle>
                    <CardDescription> {{ props.documents.total }} documents total </CardDescription>
                </div>

                <!-- Search -->
                <div class="flex items-center space-x-2">
                    <div class="relative">
                        <Search class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                        <Input v-model="searchQuery" placeholder="Search documents..." class="w-64 pl-8" @keyup.enter="handleSearch" />
                    </div>
                    <Button variant="outline" size="sm" :disabled="isLoading" @click="handleSearch">
                        <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
                        <Search v-else class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Documents List -->
            <div v-if="hasDocuments" class="space-y-4">
                <div
                    v-for="document in props.documents.data"
                    :key="document.id"
                    class="flex items-center gap-4 rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
                >
                    <!-- File Icon -->
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-muted">
                            <component :is="getFileIcon(document.mime_type)" class="h-6 w-6 text-muted-foreground" />
                        </div>
                    </div>

                    <!-- Document Info -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="truncate text-sm font-medium">{{ document.title }}</h3>
                            <Badge :variant="getStatusConfig(document.processing_status).variant" class="flex items-center gap-1">
                                <component
                                    :is="getStatusConfig(document.processing_status).icon"
                                    :class="['h-3 w-3', document.processing_status === 'processing' ? 'animate-spin' : '']"
                                />
                                {{ getStatusConfig(document.processing_status).label }}
                            </Badge>
                        </div>

                        <p class="truncate text-xs text-muted-foreground">
                            {{ document.original_filename }}
                        </p>

                        <div class="mt-2 flex items-center gap-4 text-xs text-muted-foreground">
                            <span>{{ formatFileSize(document.file_size) }}</span>
                            <span>{{ formatDate(document.uploaded_at) }}</span>
                            <div v-if="document.tags.length > 0" class="flex gap-1">
                                <Badge v-for="tag in document.tags.slice(0, 2)" :key="tag" variant="outline" class="text-xs">
                                    {{ tag }}
                                </Badge>
                                <span v-if="document.tags.length > 2" class="text-muted-foreground"> +{{ document.tags.length - 2 }} more </span>
                            </div>
                        </div>

                        <!-- Simple processing status display -->
                        <div v-if="document.processing_status === 'processing'" class="mt-3">
                            <div class="flex items-center gap-2 text-sm text-blue-600">
                                <Loader2 class="h-4 w-4 animate-spin" />
                                <span>Processing document...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" @click="viewDocument(document)">
                            <Eye class="h-4 w-4" />
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="sm">
                                    <MoreVertical class="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="downloadDocument(document)">
                                    <Download class="mr-2 h-4 w-4" />
                                    Download
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="deleteDocument(document)" class="text-destructive focus:text-destructive">
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="py-12 text-center">
                <FileText class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                <h3 class="mb-2 text-lg font-semibold">No documents found</h3>
                <p class="mb-4 text-muted-foreground">
                    {{ props.search ? 'No documents match your search.' : 'Start by uploading your first document.' }}
                </p>
                <Button
                    v-if="props.search"
                    variant="outline"
                    @click="
                        searchQuery = '';
                        handleSearch();
                    "
                >
                    Clear search
                </Button>
            </div>

            <!-- Pagination -->
            <div v-if="props.documents.last_page > 1" class="mt-6">
                <Separator class="mb-4" />
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        Showing {{ (props.documents.current_page - 1) * props.documents.per_page + 1 }} to
                        {{ Math.min(props.documents.current_page * props.documents.per_page, props.documents.total) }} of
                        {{ props.documents.total }} results
                    </p>

                    <div class="flex items-center space-x-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="props.documents.current_page <= 1"
                            @click="changePage(props.documents.current_page - 1)"
                        >
                            Previous
                        </Button>

                        <div class="flex items-center space-x-1">
                            <Button
                                v-for="page in Math.min(5, props.documents.last_page)"
                                :key="page"
                                :variant="page === props.documents.current_page ? 'default' : 'outline'"
                                size="sm"
                                @click="changePage(page)"
                            >
                                {{ page }}
                            </Button>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="props.documents.current_page >= props.documents.last_page"
                            @click="changePage(props.documents.current_page + 1)"
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
