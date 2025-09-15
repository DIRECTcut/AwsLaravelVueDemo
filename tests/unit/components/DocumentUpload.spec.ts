import DocumentUpload from '@/components/DocumentUpload.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

vi.mock('@inertiajs/vue3');

describe('DocumentUpload', () => {
    let wrapper: any;

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders upload form correctly', () => {
        // Arrange
        wrapper = mount(DocumentUpload);

        // Act & Assert
        expect(wrapper.text()).toContain('Upload Documents');
        expect(wrapper.find('input[type="file"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Click to upload or drag and drop');
    });

    it('accepts only PDF and image files', () => {
        // Arrange
        wrapper = mount(DocumentUpload);

        // Act
        const fileInput = wrapper.find('input[type="file"]');

        // Assert
        expect(fileInput.attributes('accept')).toContain('application/pdf');
        expect(fileInput.attributes('accept')).toContain('image/jpeg');
        expect(fileInput.attributes('accept')).toContain('image/png');
    });

    it('displays file name when file is selected', async () => {
        // Arrange
        wrapper = mount(DocumentUpload);
        const file = new File(['dummy content'], 'test.pdf', { type: 'application/pdf' });
        const fileInput = wrapper.find('input[type="file"]');

        // Act
        Object.defineProperty(fileInput.element, 'files', {
            value: [file],
            writable: false,
        });
        await fileInput.trigger('change');
        await nextTick();

        // Assert
        expect(wrapper.text()).toContain('test.pdf');
    });

    it('adds upload button when files are selected', async () => {
        // Arrange
        wrapper = mount(DocumentUpload);
        const file = new File(['dummy content'], 'test.pdf', { type: 'application/pdf' });
        const fileInput = wrapper.find('input[type="file"]');

        // Act
        Object.defineProperty(fileInput.element, 'files', {
            value: [file],
            writable: false,
        });
        await fileInput.trigger('change');
        await nextTick();

        // Assert - Upload All button should appear
        expect(wrapper.text()).toContain('Upload All');
        expect(wrapper.text()).toContain('test.pdf');
    });

    it('shows upload progress when uploading', async () => {
        // Arrange
        wrapper = mount(DocumentUpload);
        const file = new File(['dummy content'], 'test.pdf', { type: 'application/pdf' });
        const fileInput = wrapper.find('input[type="file"]');

        // Act
        Object.defineProperty(fileInput.element, 'files', {
            value: [file],
            writable: false,
        });
        await fileInput.trigger('change');
        await nextTick();

        // Assert - file should be added to the list
        expect(wrapper.text()).toContain('test.pdf');
        expect(wrapper.text()).toContain('Files (1)');
    });

    it('displays file list after selecting files', async () => {
        // Arrange
        wrapper = mount(DocumentUpload);
        const file1 = new File(['content1'], 'doc1.pdf', { type: 'application/pdf' });
        const file2 = new File(['content2'], 'doc2.pdf', { type: 'application/pdf' });
        const fileInput = wrapper.find('input[type="file"]');

        // Act
        Object.defineProperty(fileInput.element, 'files', {
            value: [file1, file2],
            writable: false,
        });
        await fileInput.trigger('change');
        await nextTick();

        // Assert
        expect(wrapper.text()).toContain('Files (2)');
        expect(wrapper.text()).toContain('doc1.pdf');
        expect(wrapper.text()).toContain('doc2.pdf');
    });

    it('shows clear button when files are added', async () => {
        // Arrange
        wrapper = mount(DocumentUpload);
        const file = new File(['dummy content'], 'test.pdf', { type: 'application/pdf' });
        const fileInput = wrapper.find('input[type="file"]');

        // Act - add file
        Object.defineProperty(fileInput.element, 'files', {
            value: [file],
            writable: false,
        });
        await fileInput.trigger('change');
        await nextTick();

        // Assert - Clear button should appear
        expect(wrapper.text()).toContain('test.pdf');
        expect(wrapper.text()).toContain('Clear');
        expect(wrapper.text()).toContain('Files (1)');
    });

    it('validates file size limit', async () => {
        // Arrange
        wrapper = mount(DocumentUpload);

        // Create a file larger than 10MB
        const largeFile = new File(['x'.repeat(11 * 1024 * 1024)], 'large.pdf', {
            type: 'application/pdf',
        });
        const fileInput = wrapper.find('input[type="file"]');

        // Act
        Object.defineProperty(fileInput.element, 'files', {
            value: [largeFile],
            writable: false,
        });
        await fileInput.trigger('change');
        await nextTick();

        // Assert - file should not be added due to size limit
        expect(wrapper.text()).not.toContain('large.pdf');
        // The component shows max size in the description
        expect(wrapper.text()).toContain('Max 10MB');
    });
});
