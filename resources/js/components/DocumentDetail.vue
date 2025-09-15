<template>
    <div class="space-y-6">
        <!-- Document Header -->
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ document.name }}</h1>
                    <div class="mt-2 flex items-center gap-4 text-sm text-gray-500">
                        <span>{{ formatFileType(document.mime_type) }}</span>
                        <span>{{ formatFileSize(document.size) }}</span>
                        <span>Uploaded {{ formatDate(document.created_at) }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span :class="['rounded-full px-3 py-1 text-xs font-medium', statusClasses[document.status]]">
                        {{ statusLabels[document.status] }}
                    </span>
                    <Button v-if="document.s3_url" @click="downloadDocument" variant="outline" size="sm">
                        <Download class="mr-2 h-4 w-4" />
                        Download
                    </Button>
                </div>
            </div>
        </div>

        <!-- Processing Status -->
        <div v-if="document.status === 'processing'" class="rounded-lg border border-yellow-200 bg-yellow-50 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 animate-pulse">
                    <div class="h-4 w-4 rounded-full bg-yellow-400"></div>
                </div>
                <p class="ml-3 text-sm text-yellow-800">Document is being processed. This may take a few moments...</p>
            </div>
        </div>

        <div v-else-if="document.status === 'failed'" class="rounded-lg border border-red-200 bg-red-50 p-6">
            <p class="text-sm text-red-800">Processing failed. Please try uploading the document again.</p>
        </div>

        <!-- Extracted Text -->
        <div v-if="document.textract_data" class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Extracted Text</h2>

            <div v-if="document.textract_data.Blocks && document.textract_data.Blocks.length > 0" class="space-y-2">
                <div v-for="(block, index) in document.textract_data.Blocks" :key="index" class="border-l-4 border-gray-200 py-2 pl-4">
                    <p class="text-gray-800">{{ block.Text }}</p>
                    <span class="text-xs text-gray-500"> Confidence: {{ block.Confidence?.toFixed(1) }}% </span>
                </div>
            </div>
            <p v-else class="text-gray-500">No text extracted yet</p>

            <!-- Tables -->
            <div v-if="document.textract_data.tables && document.textract_data.tables.length > 0" class="mt-6">
                <h3 class="text-md mb-2 font-medium">Tables Detected: {{ document.textract_data.tables.length }}</h3>
                <div v-for="(table, index) in document.textract_data.tables" :key="index" class="text-sm text-gray-600">
                    Table {{ index + 1 }}: {{ table.rows }} rows × {{ table.columns }} columns
                </div>
            </div>

            <!-- Forms -->
            <div v-if="document.textract_data.forms && document.textract_data.forms.length > 0" class="mt-6">
                <h3 class="text-md mb-2 font-medium">Form Fields</h3>
                <div class="space-y-1">
                    <div v-for="(form, index) in document.textract_data.forms" :key="index" class="text-sm">
                        <span class="font-medium text-gray-700">{{ form.key }}:</span>
                        <span class="ml-2 text-gray-600">{{ form.value }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div v-else-if="document.status === 'completed'" class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Extracted Text</h2>
            <p class="text-gray-500">No text extracted yet</p>
        </div>

        <!-- Sentiment Analysis -->
        <div v-if="document.comprehend_data" class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Sentiment Analysis</h2>

            <div v-if="document.comprehend_data.Sentiment" class="mb-6">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium">Sentiment: {{ document.comprehend_data.Sentiment }}</span>
                </div>
                <div v-if="document.comprehend_data.SentimentScore" class="space-y-2">
                    <div v-for="(score, sentiment) in document.comprehend_data.SentimentScore" :key="sentiment">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">{{ sentiment }}</span>
                            <span class="font-medium">{{ (score * 100).toFixed(0) }}%</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full" :class="sentimentColors[sentiment]" :style="{ width: `${score * 100}%` }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Entities -->
            <div v-if="document.comprehend_data.Entities && document.comprehend_data.Entities.length > 0" class="mb-6">
                <h3 class="text-md mb-2 font-medium">Detected Entities</h3>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="(entity, index) in document.comprehend_data.Entities"
                        :key="index"
                        class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800"
                    >
                        {{ entity.Text }}
                        <span class="ml-1 text-blue-600">({{ entity.Type }})</span>
                    </span>
                </div>
            </div>

            <!-- Key Phrases -->
            <div v-if="document.comprehend_data.KeyPhrases && document.comprehend_data.KeyPhrases.length > 0">
                <h3 class="text-md mb-2 font-medium">Key Phrases</h3>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="(phrase, index) in document.comprehend_data.KeyPhrases"
                        :key="index"
                        class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800"
                    >
                        {{ phrase.Text }}
                    </span>
                </div>
            </div>
        </div>
        <div v-else-if="document.status === 'completed'" class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Sentiment Analysis</h2>
            <p class="text-gray-500">No sentiment analysis available</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Download } from 'lucide-vue-next';

interface Props {
    document: {
        id: number;
        name: string;
        mime_type: string;
        size: number;
        status: string;
        s3_key: string;
        s3_url?: string;
        created_at: string;
        updated_at: string;
        textract_data?: any;
        comprehend_data?: any;
    };
}

const props = defineProps<Props>();

const statusClasses = {
    pending: 'bg-gray-100 text-gray-800',
    processing: 'bg-yellow-100 text-yellow-800 animate-pulse',
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
};

const statusLabels = {
    pending: 'Pending',
    processing: 'Processing',
    completed: 'Completed',
    failed: 'Failed',
};

const sentimentColors = {
    Positive: 'bg-green-500',
    Negative: 'bg-red-500',
    Neutral: 'bg-gray-500',
    Mixed: 'bg-yellow-500',
};

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatFileType(mimeType: string): string {
    const types: Record<string, string> = {
        'application/pdf': 'PDF',
        'image/jpeg': 'JPEG',
        'image/jpg': 'JPG',
        'image/png': 'PNG',
    };
    return types[mimeType] || mimeType;
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function downloadDocument() {
    if (props.document.s3_url) {
        window.open(props.document.s3_url, '_blank');
    }
}
</script>
