<template>
    <div v-if="status" class="processing-status">
        <div class="flex items-center space-x-2">
            <div class="status-indicator" :class="statusClass">
                <div v-if="status.status === 'processing'" class="animate-spin">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="m12 2a10 10 0 0 1 10 10h-2a8 8 0 0 0-8-8z"></path>
                    </svg>
                </div>
                <div v-else-if="status.status === 'completed'" class="text-green-600">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
                <div v-else-if="status.status === 'failed'" class="text-red-600">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
                <div v-else class="text-gray-400">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
            </div>

            <div class="flex-1">
                <div class="text-sm font-medium">{{ status.status_label }}</div>
                <div v-if="status.message" class="text-xs text-gray-600">{{ status.message }}</div>
            </div>

            <div class="text-xs text-gray-500">{{ status.progress }}%</div>
        </div>

        <!-- Progress bar -->
        <div v-if="status.status === 'processing'" class="mt-2">
            <div class="h-2 w-full rounded-full bg-gray-200">
                <div class="h-2 rounded-full bg-blue-600 transition-all duration-300" :style="{ width: `${status.progress}%` }"></div>
            </div>
        </div>

        <!-- Metadata display for completed status -->
        <div v-if="status.status === 'completed' && status.metadata" class="mt-2 text-xs text-gray-500">
            <div v-if="status.metadata.confidence" class="flex justify-between">
                <span>Confidence:</span>
                <span>{{ Math.round((status.metadata.confidence || 0) * 100) }}%</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, watchEffect } from 'vue';

interface ProcessingStatus {
    document_id: number;
    document_title: string;
    status: string;
    status_label: string;
    message: string | null;
    metadata: Record<string, any> | null;
    timestamp: string;
    progress: number;
}

interface ProcessingUpdate {
    [documentId: string]: ProcessingStatus;
}

interface Props {
    processingUpdates: ProcessingUpdate;
    autoHide?: boolean;
    hideDelay?: number;
}

const props = withDefaults(defineProps<Props>(), {
    autoHide: true,
    hideDelay: 5000,
});

// Get the most recent processing status from any document
const status = computed(() => {
    if (!props.processingUpdates) return null;

    const updates = Object.values(props.processingUpdates);
    if (updates.length === 0) return null;

    // Return the most recent update
    return updates.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())[0];
});

const statusClass = computed(() => {
    if (!status.value) return '';

    switch (status.value.status) {
        case 'processing':
            return 'text-blue-600';
        case 'completed':
            return 'text-green-600';
        case 'failed':
            return 'text-red-600';
        default:
            return 'text-gray-400';
    }
});

// Auto-hide completed/failed status after delay
if (props.autoHide) {
    watchEffect(() => {
        if (status.value && ['completed', 'failed'].includes(status.value.status)) {
            setTimeout(() => {
                if (status.value && ['completed', 'failed'].includes(status.value.status)) {
                    status.value = null;
                }
            }, props.hideDelay);
        }
    });
}
</script>

<style scoped>
.processing-status {
    padding: 0.75rem;
    background-color: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.status-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
}
</style>
