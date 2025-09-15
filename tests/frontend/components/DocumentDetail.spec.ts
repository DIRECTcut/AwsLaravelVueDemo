import DocumentDetail from '@/components/DocumentDetail.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

describe('DocumentDetail', () => {
    let wrapper: any;
    const mockDocument = {
        id: 1,
        name: 'test-document.pdf',
        mime_type: 'application/pdf',
        size: 1024000,
        status: 'completed',
        s3_key: 'documents/test-doc.pdf',
        s3_url: 'https://s3.amazonaws.com/bucket/test-doc.pdf',
        created_at: '2024-01-01T12:00:00Z',
        updated_at: '2024-01-01T12:30:00Z',
        textract_data: {
            Blocks: [
                { Text: 'Sample text from document', Confidence: 99.5, BlockType: 'LINE' },
                { Text: 'Another line of text', Confidence: 98.2, BlockType: 'LINE' },
            ],
            tables: [{ rows: 3, columns: 2, cells: [] }],
            forms: [
                { key: 'Name', value: 'John Doe' },
                { key: 'Date', value: '2024-01-01' },
            ],
        },
        comprehend_data: {
            Sentiment: 'POSITIVE',
            SentimentScore: {
                Positive: 0.85,
                Negative: 0.05,
                Neutral: 0.08,
                Mixed: 0.02,
            },
            Entities: [
                { Text: 'John Doe', Type: 'PERSON', Score: 0.95 },
                { Text: '2024-01-01', Type: 'DATE', Score: 0.99 },
            ],
            KeyPhrases: [
                { Text: 'important document', Score: 0.92 },
                { Text: 'legal agreement', Score: 0.88 },
            ],
        },
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders document details correctly', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.find('h1').text()).toBe('test-document.pdf');
        expect(wrapper.text()).toContain('1000 KB');
        expect(wrapper.text()).toContain('PDF');
    });

    it('displays processing status badge', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Completed');
    });

    it('shows extracted text when available', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Sample text from document');
        expect(wrapper.text()).toContain('Another line of text');
    });

    it('displays sentiment analysis results', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Sentiment: POSITIVE');
        expect(wrapper.text()).toContain('85%'); // Positive score
    });

    it('shows detected entities', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('John Doe');
        expect(wrapper.text()).toContain('PERSON');
        expect(wrapper.text()).toContain('2024-01-01');
        expect(wrapper.text()).toContain('DATE');
    });

    it('displays key phrases', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('important document');
        expect(wrapper.text()).toContain('legal agreement');
    });

    it('shows table information when tables detected', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Tables Detected: 1');
        expect(wrapper.text()).toContain('3 rows × 2 columns');
    });

    it('displays form fields when forms detected', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Name:');
        expect(wrapper.text()).toContain('John Doe');
        expect(wrapper.text()).toContain('Date:');
        expect(wrapper.text()).toContain('2024-01-01');
    });

    it('shows processing message for processing documents', () => {
        // Arrange
        const processingDoc = {
            ...mockDocument,
            status: 'processing',
            textract_data: null,
            comprehend_data: null,
        };

        wrapper = mount(DocumentDetail, {
            props: {
                document: processingDoc,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Document is being processed');
        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });

    it('displays error message for failed documents', () => {
        // Arrange
        const failedDoc = {
            ...mockDocument,
            status: 'failed',
            textract_data: null,
            comprehend_data: null,
        };

        wrapper = mount(DocumentDetail, {
            props: {
                document: failedDoc,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('Processing failed');
    });

    it('shows download button', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        const buttons = wrapper.findAll('button');
        const downloadButton = buttons.find((b: any) => b.text().includes('Download'));
        expect(downloadButton).toBeDefined();
    });

    it('triggers download when button clicked', async () => {
        // Arrange
        global.window.open = vi.fn();
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act
        const buttons = wrapper.findAll('button');
        const downloadButton = buttons.find((b: any) => b.text().includes('Download'));
        if (downloadButton) {
            await downloadButton.trigger('click');

            // Assert
            expect(global.window.open).toHaveBeenCalledWith(mockDocument.s3_url, '_blank');
        }
    });

    it('shows confidence scores for extracted text', () => {
        // Arrange
        wrapper = mount(DocumentDetail, {
            props: {
                document: mockDocument,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('99.5%'); // First block confidence
        expect(wrapper.text()).toContain('98.2%'); // Second block confidence
    });

    it('handles documents without AWS processing data', () => {
        // Arrange
        const basicDoc = {
            ...mockDocument,
            textract_data: null,
            comprehend_data: null,
        };

        wrapper = mount(DocumentDetail, {
            props: {
                document: basicDoc,
            },
        });

        // Act & Assert
        expect(wrapper.text()).toContain('No text extracted yet');
        expect(wrapper.text()).toContain('No sentiment analysis available');
    });
});
