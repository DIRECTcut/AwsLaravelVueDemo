<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle, FileText, Image, Loader2, Upload, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

interface UploadFile {
    file: File;
    id: string;
    progress: number;
    status: 'pending' | 'uploading' | 'processing' | 'completed' | 'error';
    error?: string;
    preview?: string;
}

const isDragOver = ref(false);
const files = ref<UploadFile[]>([]);
const fileInput = ref<HTMLInputElement>();

const emit = defineEmits<{
    uploaded: [document: any];
}>();

const supportedTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

const maxFileSize = 10 * 1024 * 1024; // 10MB

const hasFiles = computed(() => files.value.length > 0);
const canUpload = computed(() => files.value.some((f) => f.status === 'pending'));

function generateFileId(): string {
    return Math.random().toString(36).substring(2) + Date.now().toString(36);
}

function getFileIcon(file: File): any {
    if (file.type.startsWith('image/')) return Image;
    return FileText;
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function createImagePreview(file: File): Promise<string> {
    return new Promise((resolve) => {
        if (!file.type.startsWith('image/')) {
            resolve('');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => resolve((e.target?.result as string) || '');
        reader.readAsDataURL(file);
    });
}

async function addFiles(fileList: FileList | File[]) {
    const newFiles: UploadFile[] = [];

    for (const file of Array.from(fileList)) {
        // Validate file type
        if (!supportedTypes.includes(file.type)) {
            continue;
        }

        // Validate file size
        if (file.size > maxFileSize) {
            continue;
        }

        // Check for duplicates
        if (files.value.some((f) => f.file.name === file.name && f.file.size === file.size)) {
            continue;
        }

        const preview = await createImagePreview(file);

        newFiles.push({
            file,
            id: generateFileId(),
            progress: 0,
            status: 'pending',
            preview,
        });
    }

    files.value.push(...newFiles);
}

function removeFile(id: string) {
    files.value = files.value.filter((f) => f.id !== id);
}

function clearFiles() {
    files.value = files.value.filter((f) => f.status === 'uploading' || f.status === 'processing');
}

async function uploadFiles() {
    const pendingFiles = files.value.filter((f) => f.status === 'pending');

    for (const uploadFile of pendingFiles) {
        uploadFile.status = 'uploading';
        uploadFile.progress = 50;

        try {
            // Use Inertia.js for proper CSRF handling
            router.post(
                '/documents',
                {
                    file: uploadFile.file,
                    title: uploadFile.file.name,
                },
                {
                    forceFormData: true,
                    onProgress: (progress) => {
                        uploadFile.progress = (progress?.percentage || 0) * 100;
                    },
                    onSuccess: (response) => {
                        uploadFile.status = 'completed';
                        uploadFile.progress = 100;
                        emit('uploaded', response);
                    },
                    onError: (errors) => {
                        uploadFile.status = 'error';
                        uploadFile.error = (Object.values(errors)[0] as string) || 'Upload failed';
                    },
                    onFinish: () => {
                        // Upload finished (success or error)
                    },
                },
            );
        } catch (error) {
            uploadFile.status = 'error';
            uploadFile.error = error instanceof Error ? error.message : 'Upload failed';
        }
    }
}

function onDragOver(e: DragEvent) {
    e.preventDefault();
    isDragOver.value = true;
}

function onDragLeave(e: DragEvent) {
    e.preventDefault();
    isDragOver.value = false;
}

function onDrop(e: DragEvent) {
    e.preventDefault();
    isDragOver.value = false;

    if (e.dataTransfer?.files) {
        addFiles(e.dataTransfer.files);
    }
}

function onFileSelect(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files) {
        addFiles(input.files);
        input.value = ''; // Reset input
    }
}

function openFileDialog() {
    fileInput.value?.click();
}

// Cleanup object URLs on unmount
onUnmounted(() => {
    files.value.forEach((f) => {
        if (f.preview) {
            URL.revokeObjectURL(f.preview);
        }
    });
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Upload class="h-5 w-5" />
                Upload Documents
            </CardTitle>
            <CardDescription> Drag and drop files here or click to browse. Supports PDF, images, and text documents up to 10MB. </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Drop Zone -->
            <div
                class="relative cursor-pointer rounded-lg border-2 border-dashed transition-colors"
                :class="[
                    isDragOver ? 'border-primary bg-primary/5' : 'border-border/70 hover:border-border',
                    'flex min-h-[200px] items-center justify-center p-8',
                ]"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
                @click="openFileDialog"
            >
                <input ref="fileInput" type="file" multiple :accept="supportedTypes.join(',')" class="hidden" @change="onFileSelect" />

                <div class="space-y-2 text-center">
                    <Upload class="mx-auto h-12 w-12 text-muted-foreground" />
                    <div>
                        <p class="text-sm font-medium">Click to upload or drag and drop</p>
                        <p class="text-xs text-muted-foreground">PDF, Images, Documents (Max 10MB each)</p>
                    </div>
                </div>
            </div>

            <!-- File List -->
            <div v-if="hasFiles" class="space-y-3">
                <Separator />

                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-medium">Files ({{ files.length }})</h4>
                    <div class="space-x-2">
                        <Button v-if="canUpload" size="sm" @click="uploadFiles"> Upload All </Button>
                        <Button variant="outline" size="sm" @click="clearFiles"> Clear </Button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div v-for="uploadFile in files" :key="uploadFile.id" class="flex items-center gap-3 rounded-lg border bg-card p-3">
                        <!-- File Icon/Preview -->
                        <div class="flex-shrink-0">
                            <div v-if="uploadFile.preview" class="h-10 w-10 overflow-hidden rounded">
                                <img :src="uploadFile.preview" :alt="uploadFile.file.name" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="flex h-10 w-10 items-center justify-center rounded bg-muted">
                                <component :is="getFileIcon(uploadFile.file)" class="h-5 w-5 text-muted-foreground" />
                            </div>
                        </div>

                        <!-- File Info -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ uploadFile.file.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatFileSize(uploadFile.file.size) }}
                            </p>

                            <!-- Progress Bar -->
                            <div v-if="uploadFile.status === 'uploading'" class="mt-2">
                                <div class="h-1 w-full rounded-full bg-muted">
                                    <div
                                        class="h-1 rounded-full bg-primary transition-all duration-300"
                                        :style="{ width: `${uploadFile.progress}%` }"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">Uploading... {{ Math.round(uploadFile.progress) }}%</p>
                            </div>

                            <!-- Error Message -->
                            <div v-if="uploadFile.error" class="mt-2">
                                <p class="text-xs text-destructive">{{ uploadFile.error }}</p>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="flex items-center gap-2">
                            <Badge
                                :variant="
                                    uploadFile.status === 'completed'
                                        ? 'default'
                                        : uploadFile.status === 'error'
                                          ? 'destructive'
                                          : uploadFile.status === 'processing'
                                            ? 'secondary'
                                            : 'outline'
                                "
                                class="flex items-center gap-1"
                            >
                                <Loader2
                                    v-if="uploadFile.status === 'uploading' || uploadFile.status === 'processing'"
                                    class="h-3 w-3 animate-spin"
                                />
                                <CheckCircle v-else-if="uploadFile.status === 'completed'" class="h-3 w-3" />
                                <AlertCircle v-else-if="uploadFile.status === 'error'" class="h-3 w-3" />
                                <span class="capitalize">{{ uploadFile.status }}</span>
                            </Badge>

                            <!-- Remove Button -->
                            <Button
                                v-if="uploadFile.status === 'pending' || uploadFile.status === 'error'"
                                variant="ghost"
                                size="sm"
                                @click="removeFile(uploadFile.id)"
                            >
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
