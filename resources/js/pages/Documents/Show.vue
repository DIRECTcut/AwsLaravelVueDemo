<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsItem, TabsList } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ProcessingJob {
    id: number;
    job_type: string;
    status: string;
    created_at: string;
    completed_at: string | null;
    output_data: Record<string, any> | null;
    error_message: string | null;
}

interface AnalysisResult {
    id: number;
    analysis_type: string;
    processed_data: Record<string, any>;
    confidence_score: number | null;
    created_at: string;
}

interface Document {
    id: number;
    title: string;
    original_filename: string;
    file_size: number;
    mime_type: string;
    processing_status: string;
    description: string | null;
    tags: string[];
    uploaded_at: string;
    processing_jobs: ProcessingJob[];
    analysis_results: AnalysisResult[];
}

interface Props {
    document: Document;
    downloadUrl: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: '/documents',
    },
    {
        title: props.document.title,
        href: `/documents/${props.document.id}`,
    },
];

const statusColor = computed(() => {
    switch (props.document.processing_status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'processing':
            return 'bg-yellow-100 text-yellow-800';
        case 'failed':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
});

const formatFileSize = (bytes: number): string => {
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    if (bytes === 0) return '0 Bytes';
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round((bytes / Math.pow(1024, i)) * 100) / 100 + ' ' + sizes[i];
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleString();
};

const textractResults = computed(() => {
    if (!props.document?.analysis_results) return [];
    return props.document.analysis_results.filter((result) => result && result.analysis_type && result.analysis_type.startsWith('textract'));
});

const comprehendResults = computed(() => {
    if (!props.document?.analysis_results) return [];
    return props.document.analysis_results.filter((result) => result && result.analysis_type && result.analysis_type.startsWith('comprehend'));
});

const extractedText = computed(() => {
    // Look for any textract result (text or analysis)
    const textResult = textractResults.value.find(
        (result) => result.analysis_type === 'textract_text' || result.analysis_type === 'textract_analysis',
    );

    // Extract text from text_blocks
    if (textResult?.processed_data?.text_blocks) {
        const texts = textResult.processed_data.text_blocks.map((block: any) => block.text);
        return texts.join('\n');
    }

    return textResult?.processed_data?.text || '';
});

const sentimentAnalysis = computed(() => {
    const sentimentResult = comprehendResults.value.find((result) => result.analysis_type === 'comprehend_sentiment');
    return sentimentResult?.processed_data;
});

const entities = computed(() => {
    const entitiesResult = comprehendResults.value.find((result) => result.analysis_type === 'comprehend_entities');
    return entitiesResult?.processed_data?.entities || [];
});

const keyPhrases = computed(() => {
    const keyPhrasesResult = comprehendResults.value.find((result) => result.analysis_type === 'comprehend_key_phrases');
    return keyPhrasesResult?.processed_data?.key_phrases || [];
});
</script>

<template>
    <Head :title="document?.title || 'Document'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div v-if="!document" class="flex h-full flex-1 items-center justify-center">
            <p>Loading document...</p>
        </div>
        <div v-else class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">{{ document.title }}</h1>
                    <p class="text-muted-foreground">{{ document.original_filename }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Badge :class="statusColor">
                        {{ document.processing_status }}
                    </Badge>
                    <Button as-child>
                        <a :href="downloadUrl" target="_blank">Download</a>
                    </Button>
                </div>
            </div>

            <!-- Document Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Document Information</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">File Size</dt>
                        <dd class="text-sm">{{ formatFileSize(document.file_size) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">Type</dt>
                        <dd class="text-sm">{{ document.mime_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">Uploaded</dt>
                        <dd class="text-sm">{{ formatDate(document.uploaded_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">Status</dt>
                        <dd class="text-sm">{{ document.processing_status }}</dd>
                    </div>
                    <div v-if="document.description" class="col-span-2 md:col-span-4">
                        <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                        <dd class="text-sm">{{ document.description }}</dd>
                    </div>
                    <div v-if="document.tags.length" class="col-span-2 md:col-span-4">
                        <dt class="text-sm font-medium text-muted-foreground">Tags</dt>
                        <dd class="flex flex-wrap gap-1">
                            <Badge v-for="tag in document.tags" :key="tag" variant="outline">
                                {{ tag }}
                            </Badge>
                        </dd>
                    </div>
                </CardContent>
            </Card>

            <!-- Processing Results -->
            <div v-if="document.processing_status === 'completed'" class="space-y-6">
                <Tabs default-value="text" class="w-full">
                    <TabsList>
                        <TabsItem value="text">Extracted Text</TabsItem>
                        <TabsItem value="sentiment">Sentiment Analysis</TabsItem>
                        <TabsItem value="entities">Entities</TabsItem>
                        <TabsItem value="phrases">Key Phrases</TabsItem>
                        <TabsItem value="jobs">Processing Jobs</TabsItem>
                    </TabsList>

                    <!-- Extracted Text -->
                    <TabsContent value="text">
                        <Card>
                            <CardHeader>
                                <CardTitle>Extracted Text</CardTitle>
                                <CardDescription>Text content extracted from the document using AWS Textract</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="extractedText" class="rounded-md bg-muted p-4 text-sm whitespace-pre-wrap">
                                    {{ extractedText }}
                                </div>
                                <p v-else class="text-muted-foreground">No text content extracted</p>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Sentiment Analysis -->
                    <TabsContent value="sentiment">
                        <Card>
                            <CardHeader>
                                <CardTitle>Sentiment Analysis</CardTitle>
                                <CardDescription>Emotional tone analysis using AWS Comprehend</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="sentimentAnalysis" class="space-y-4">
                                    <div>
                                        <h4 class="font-medium">Overall Sentiment</h4>
                                        <Badge
                                            :class="
                                                sentimentAnalysis.sentiment === 'POSITIVE'
                                                    ? 'bg-green-100 text-green-800'
                                                    : sentimentAnalysis.sentiment === 'NEGATIVE'
                                                      ? 'bg-red-100 text-red-800'
                                                      : sentimentAnalysis.sentiment === 'NEUTRAL'
                                                        ? 'bg-gray-100 text-gray-800'
                                                        : 'bg-yellow-100 text-yellow-800'
                                            "
                                        >
                                            {{ sentimentAnalysis.sentiment }}
                                        </Badge>
                                    </div>
                                    <div v-if="sentimentAnalysis.confidence_scores">
                                        <h4 class="mb-2 font-medium">Confidence Scores</h4>
                                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                                            <div v-for="(score, sentiment) in sentimentAnalysis.confidence_scores" :key="sentiment">
                                                <dt class="text-sm font-medium text-muted-foreground capitalize">{{ sentiment }}</dt>
                                                <dd class="text-sm">{{ Math.round(score * 100) }}%</dd>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-muted-foreground">No sentiment analysis available</p>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Entities -->
                    <TabsContent value="entities">
                        <Card>
                            <CardHeader>
                                <CardTitle>Named Entities</CardTitle>
                                <CardDescription>People, organizations, locations, and other entities detected in the text</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="entities.length" class="space-y-2">
                                    <div
                                        v-for="entity in entities"
                                        :key="entity.text"
                                        class="flex items-center justify-between rounded-md border p-3"
                                    >
                                        <div>
                                            <span class="font-medium">{{ entity.text }}</span>
                                            <Badge variant="outline" class="ml-2">{{ entity.type }}</Badge>
                                        </div>
                                        <span class="text-sm text-muted-foreground">{{ Math.round(entity.confidence * 100) }}%</span>
                                    </div>
                                </div>
                                <p v-else class="text-muted-foreground">No entities detected</p>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Key Phrases -->
                    <TabsContent value="phrases">
                        <Card>
                            <CardHeader>
                                <CardTitle>Key Phrases</CardTitle>
                                <CardDescription>Important phrases and topics extracted from the text</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="keyPhrases.length" class="flex flex-wrap gap-2">
                                    <Badge v-for="phrase in keyPhrases" :key="phrase.text" variant="secondary">
                                        {{ phrase.text }}
                                        <span class="ml-1 text-xs opacity-70">{{ Math.round(phrase.confidence * 100) }}%</span>
                                    </Badge>
                                </div>
                                <p v-else class="text-muted-foreground">No key phrases detected</p>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Processing Jobs -->
                    <TabsContent value="jobs">
                        <Card>
                            <CardHeader>
                                <CardTitle>Processing Jobs</CardTitle>
                                <CardDescription>Detailed information about document processing steps</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div v-for="job in document.processing_jobs" :key="job.id" class="rounded-md border p-4">
                                        <div class="mb-2 flex items-center justify-between">
                                            <h4 class="font-medium">{{ job.job_type }}</h4>
                                            <Badge
                                                :class="
                                                    job.status === 'completed'
                                                        ? 'bg-green-100 text-green-800'
                                                        : job.status === 'failed'
                                                          ? 'bg-red-100 text-red-800'
                                                          : 'bg-yellow-100 text-yellow-800'
                                                "
                                            >
                                                {{ job.status }}
                                            </Badge>
                                        </div>
                                        <div class="space-y-1 text-sm text-muted-foreground">
                                            <p>Started: {{ formatDate(job.created_at) }}</p>
                                            <p v-if="job.completed_at">Completed: {{ formatDate(job.completed_at) }}</p>
                                            <p v-if="job.error_message" class="text-red-600">Error: {{ job.error_message }}</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Processing Status -->
            <Card v-else-if="document.processing_status === 'processing'">
                <CardHeader>
                    <CardTitle>Processing in Progress</CardTitle>
                    <CardDescription
                        >Your document is currently being analyzed. Results will appear here when processing is complete.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div v-for="job in document.processing_jobs" :key="job.id" class="flex items-center justify-between rounded-md border p-3">
                            <span class="font-medium">{{ job.job_type }}</span>
                            <Badge
                                :class="
                                    job.status === 'completed'
                                        ? 'bg-green-100 text-green-800'
                                        : job.status === 'failed'
                                          ? 'bg-red-100 text-red-800'
                                          : 'bg-yellow-100 text-yellow-800'
                                "
                            >
                                {{ job.status }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Failed Status -->
            <Card v-else-if="document.processing_status === 'failed'">
                <CardHeader>
                    <CardTitle>Processing Failed</CardTitle>
                    <CardDescription
                        >There was an error processing your document. Please check the details below or try uploading again.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="job in document.processing_jobs.filter((j) => j.status === 'failed')"
                            :key="job.id"
                            class="rounded-md border border-red-200 p-3"
                        >
                            <h4 class="font-medium text-red-800">{{ job.job_type }}</h4>
                            <p class="text-sm text-red-600">{{ job.error_message }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
