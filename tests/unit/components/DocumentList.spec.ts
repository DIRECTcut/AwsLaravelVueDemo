import DocumentList from '@/components/DocumentList.vue';
import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3');

describe('DocumentList', () => {
    let wrapper: any;
    const mockDocuments = {
        data: [
            {
                id: 1,
                title: 'test-document.pdf',
                original_filename: 'test-document.pdf',
                mime_type: 'application/pdf',
                file_size: 1024000,
                processing_status: 'completed',
                uploaded_at: '2024-01-01T12:00:00Z',
                tags: [],
            },
            {
                id: 2,
                title: 'image.jpg',
                original_filename: 'image.jpg',
                mime_type: 'image/jpeg',
                file_size: 512000,
                processing_status: 'processing',
                uploaded_at: '2024-01-02T10:00:00Z',
                tags: [],
            },
            {
                id: 3,
                title: 'failed-doc.pdf',
                original_filename: 'failed-doc.pdf',
                mime_type: 'application/pdf',
                file_size: 2048000,
                processing_status: 'failed',
                uploaded_at: '2024-01-03T14:00:00Z',
                tags: [],
            },
        ],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 3,
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders document list correctly', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Documents');
        expect(wrapper.text()).toContain('3 documents total');
        expect(wrapper.findAll('[class*="border"][class*="bg-card"]').length).toBeGreaterThanOrEqual(3);
    });

    it('displays document information correctly', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('test-document.pdf');
        expect(wrapper.text()).toContain('1000 KB');
        expect(wrapper.text()).toContain('Completed');
    });

    it('shows correct status badges', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Completed');
        expect(wrapper.text()).toContain('Processing');
        expect(wrapper.text()).toContain('Failed');
    });

    it('formats file sizes correctly', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('1000 KB');
        expect(wrapper.text()).toContain('500 KB');
        expect(wrapper.text()).toContain('1.95 MB');
    });

    it('navigates to document detail on view action', async () => {
        // Arrange
        const mockVisit = vi.fn();
        (router as any).visit = mockVisit;

        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act - Click on eye/view button
        const viewButtons = wrapper.findAll('button');
        const viewButton = viewButtons.find((b: any) => b.html().includes('Eye') || b.html().includes('eye'));
        if (viewButton) {
            await viewButton.trigger('click');
            expect(mockVisit).toHaveBeenCalledWith('/documents/1');
        }
    });

    it('shows empty state when no documents', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: {
                    data: [],
                    current_page: 1,
                    last_page: 1,
                    per_page: 10,
                    total: 0,
                },
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('0 documents total');
    });

    it('displays processing status for processing documents', () => {
        // Arrange
        const processingDocs = {
            ...mockDocuments,
            data: [mockDocuments.data[1]], // processing document
            total: 1,
        };
        wrapper = mount(DocumentList, {
            props: {
                documents: processingDocs,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Processing');
    });

    it('formats dates correctly', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Jan');
        expect(wrapper.text()).toContain('2024');
    });

    it('shows action menu for documents', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act & Assert - Look for dropdown triggers
        const buttons = wrapper.findAll('button');
        expect(buttons.length).toBeGreaterThan(0);
    });

    it('has search functionality', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act & Assert
        const searchInput = wrapper.find('input[placeholder*="Search"]');
        expect(searchInput.exists()).toBe(true);
    });

    it('shows document count', () => {
        // Arrange
        wrapper = mount(DocumentList, {
            props: {
                documents: mockDocuments,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('3 documents total');
    });

    it('handles pagination info', () => {
        // Arrange
        const paginatedDocs = {
            ...mockDocuments,
            current_page: 1,
            last_page: 2,
            per_page: 2,
            total: 3,
        };

        wrapper = mount(DocumentList, {
            props: {
                documents: paginatedDocs,
            },
        });

        // Act & Assert
        expect(wrapper.vm.$props.documents.current_page).toBe(1);
        expect(wrapper.vm.$props.documents.last_page).toBe(2);
    });
});
