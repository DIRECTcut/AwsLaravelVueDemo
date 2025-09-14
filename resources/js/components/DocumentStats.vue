<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckCircle, Clock, FileText, HardDrive, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface DocumentStats {
    total: number;
    processing: number;
    completed: number;
    failed: number;
    totalSize: number;
}

interface Props {
    stats: DocumentStats;
}

const props = defineProps<Props>();

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

const statsCards = computed(() => [
    {
        title: 'Total Documents',
        value: props.stats.total,
        icon: FileText,
        description: 'All uploaded documents',
        color: 'default',
    },
    {
        title: 'Processing',
        value: props.stats.processing,
        icon: Clock,
        description: 'Currently being analyzed',
        color: 'secondary',
    },
    {
        title: 'Completed',
        value: props.stats.completed,
        icon: CheckCircle,
        description: 'Fully processed documents',
        color: 'default',
    },
    {
        title: 'Failed',
        value: props.stats.failed,
        icon: XCircle,
        description: 'Processing errors',
        color: 'destructive',
    },
    {
        title: 'Storage Used',
        value: formatFileSize(props.stats.totalSize),
        icon: HardDrive,
        description: 'Total file size',
        color: 'outline',
    },
]);
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
        <Card v-for="stat in statsCards" :key="stat.title">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">
                    {{ stat.title }}
                </CardTitle>
                <component :is="stat.icon" class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ stat.value }}</div>
                <p class="text-xs text-muted-foreground">
                    {{ stat.description }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
