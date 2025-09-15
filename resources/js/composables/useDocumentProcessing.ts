import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { useAppToast } from './useToast';

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

export function useDocumentProcessing(userId?: number) {
    const processingUpdates = ref<ProcessingUpdate>({});
    const isConnected = ref(false);
    const connection = ref<any>(null);
    const { showSuccess, showError, showInfo } = useAppToast();

    const connect = () => {
        if (!window.Echo) {
            console.warn('Laravel Echo not available');
            return;
        }

        try {
            // Listen to user-specific private channel for all document updates
            if (userId) {
                // Use Echo's private channel for authentication
                connection.value = window.Echo.private(`user.${userId}`).listen('.document.status.updated', (data: ProcessingStatus) => {
                    // Store the update
                    processingUpdates.value[data.document_id] = data;

                    // Show toast notifications based on status
                    if (data.status === 'completed') {
                        showSuccess(`✅ ${data.document_title} processing completed!`, {
                            duration: 5000,
                        });

                        // Refresh document list after completion
                        setTimeout(() => {
                            router.reload({ only: ['documents', 'stats'] });
                        }, 1000);
                    } else if (data.status === 'failed') {
                        showError(`❌ ${data.document_title} processing failed: ${data.message || 'Unknown error'}`, {
                            duration: 8000,
                        });
                    } else if (data.status === 'processing' && data.metadata?.job_type) {
                        // Show info for major processing steps
                        const jobType = data.metadata.job_type;
                        if (jobType === 'textract_text' || jobType === 'textract_analysis') {
                            showInfo(`🔍 Extracting text from ${data.document_title}...`);
                        } else if (jobType.startsWith('comprehend_')) {
                            showInfo(`🧠 Analyzing ${data.document_title} with AI...`);
                        }
                    }
                });

                isConnected.value = true;
            }
        } catch (error) {
            console.error('Failed to connect to broadcasting:', error);
            isConnected.value = false;
        }
    };

    const disconnect = () => {
        if (connection.value) {
            connection.value.stopListening('.document.status.updated');
            window.Echo.leave(`user.${userId}`);
            connection.value = null;
        }
        isConnected.value = false;
    };

    const getDocumentStatus = (documentId: number): ProcessingStatus | null => {
        return processingUpdates.value[documentId] || null;
    };

    const clearDocumentStatus = (documentId: number) => {
        delete processingUpdates.value[documentId];
    };

    onMounted(() => {
        connect();
    });

    onUnmounted(() => {
        disconnect();
    });

    return {
        processingUpdates,
        isConnected,
        getDocumentStatus,
        clearDocumentStatus,
        connect,
        disconnect,
    };
}

// Temporarily disabled to avoid multiple channel connections
// export function useDocumentProcessingStatus(documentId: number) {
//   const { getDocumentStatus, clearDocumentStatus } = useDocumentProcessing()
//
//   const status = ref<ProcessingStatus | null>(getDocumentStatus(documentId))
//   const connection = ref<any>(null)

//   const connect = () => {
//     if (!window.Echo) {
//       console.warn('Laravel Echo not available')
//       return
//     }

//     try {
//       // Listen to document-specific channel
//       connection.value = window.Echo.private(`document.${documentId}`)
//         .listen('.document.status.updated', (event: ProcessingStatus) => {
//           status.value = event
//         })
//     } catch (error) {
//       console.error('Failed to connect to document channel:', error)
//     }
//   }

//   const disconnect = () => {
//     if (connection.value) {
//       connection.value.stopListening('.document.status.updated')
//       connection.value = null
//     }
//   }

//   const clear = () => {
//     status.value = null
//     clearDocumentStatus(documentId)
//   }

//   onMounted(() => {
//     connect()
//   })

//   onUnmounted(() => {
//     disconnect()
//   })

//   return {
//     status,
//     clear,
//     connect,
//     disconnect
//   }
// }
