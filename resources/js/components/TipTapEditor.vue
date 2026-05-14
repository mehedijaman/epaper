<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import { ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const linkUrl = ref('');
const showLinkInput = ref(false);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            link: false,
            underline: false,
        }),
        Underline,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                rel: 'noopener noreferrer',
                target: '_blank',
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder ?? '',
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
        Subscript,
        Superscript,
        TaskList,
        TaskItem.configure({ nested: true }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none min-h-[8rem] px-3 py-2 focus:outline-none',
        },
    },
    onUpdate({ editor: ed }) {
        emit('update:modelValue', ed.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        const currentHtml = editor.value?.getHTML();
        if (editor.value && value !== currentHtml) {
            editor.value.commands.setContent(value, false);
        }
    },
);

function applyLink(): void {
    if (linkUrl.value === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.value
            ?.chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: linkUrl.value })
            .run();
    }
    linkUrl.value = '';
    showLinkInput.value = false;
}

function openLinkInput(): void {
    linkUrl.value = editor.value?.getAttributes('link').href ?? '';
    showLinkInput.value = true;
}
</script>

<template>
    <div class="rounded-md border border-input bg-transparent shadow-sm focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-0.5 border-b border-input px-2 py-1.5">

            <!-- Headings -->
            <select
                title="Heading"
                class="h-7 cursor-pointer rounded border-0 bg-transparent px-1 text-xs font-medium focus:outline-none hover:bg-accent"
                :value="
                    editor?.isActive('heading', { level: 1 }) ? '1'
                    : editor?.isActive('heading', { level: 2 }) ? '2'
                    : editor?.isActive('heading', { level: 3 }) ? '3'
                    : editor?.isActive('heading', { level: 4 }) ? '4'
                    : editor?.isActive('heading', { level: 5 }) ? '5'
                    : editor?.isActive('heading', { level: 6 }) ? '6'
                    : '0'
                "
                @change="(e) => {
                    const val = (e.target as HTMLSelectElement).value;
                    if (val === '0') {
                        editor?.chain().focus().setParagraph().run();
                    } else {
                        editor?.chain().focus().toggleHeading({ level: Number(val) as 1|2|3|4|5|6 }).run();
                    }
                }"
            >
                <option value="0">Paragraph</option>
                <option value="1">Heading 1</option>
                <option value="2">Heading 2</option>
                <option value="3">Heading 3</option>
                <option value="4">Heading 4</option>
                <option value="5">Heading 5</option>
                <option value="6">Heading 6</option>
            </select>

            <div class="mx-1 h-5 w-px bg-border" />

            <!-- Bold -->
            <button
                type="button"
                title="Bold (Ctrl+B)"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('bold') }"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h8a4 4 0 0 1 0 8H6zm0 8h9a4 4 0 0 1 0 8H6z"/></svg>
            </button>

            <!-- Italic -->
            <button
                type="button"
                title="Italic (Ctrl+I)"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('italic') }"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4h4l-4 16H6zm4 0h4M6 20h4"/><path d="M10 4h4l-4 16H6" stroke="currentColor" stroke-width="0" fill="currentColor"/><text x="7" y="18" font-size="16" font-style="italic" font-family="serif">I</text></svg>
            </button>

            <!-- Underline -->
            <button
                type="button"
                title="Underline (Ctrl+U)"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('underline') }"
                @click="editor?.chain().focus().toggleUnderline().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" y1="20" x2="20" y2="20"/></svg>
            </button>

            <!-- Strikethrough -->
            <button
                type="button"
                title="Strikethrough"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('strike') }"
                @click="editor?.chain().focus().toggleStrike().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
            </button>

            <!-- Inline Code -->
            <button
                type="button"
                title="Inline Code"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('code') }"
                @click="editor?.chain().focus().toggleCode().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            </button>

            <!-- Subscript -->
            <button
                type="button"
                title="Subscript"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('subscript') }"
                @click="editor?.chain().focus().toggleSubscript().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m4 5 8 8"/><path d="m12 5-8 8"/><path d="M20 19h-4c0-1.5.44-2 1.5-2.5S20 15.33 20 14c0-.47-.17-.93-.48-1.29a2.11 2.11 0 0 0-2.62-.44c-.42.24-.74.62-.9 1.07"/></svg>
            </button>

            <!-- Superscript -->
            <button
                type="button"
                title="Superscript"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('superscript') }"
                @click="editor?.chain().focus().toggleSuperscript().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m4 19 8-8"/><path d="m12 19-8-8"/><path d="M20 12h-4c0-1.5.44-2 1.5-2.5S20 8.33 20 7c0-.47-.17-.93-.48-1.29a2.11 2.11 0 0 0-2.62-.44c-.42.24-.74.62-.9 1.07"/></svg>
            </button>

            <div class="mx-1 h-5 w-px bg-border" />

            <!-- Text Color -->
            <label title="Text Color" class="relative flex cursor-pointer items-center rounded p-1 transition-colors hover:bg-accent">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"/><path d="m6 16 6-12 6 12"/><path d="M8 12h8"/></svg>
                <input
                    type="color"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    :value="editor?.getAttributes('textStyle').color ?? '#000000'"
                    @input="(e) => editor?.chain().focus().setColor((e.target as HTMLInputElement).value).run()"
                />
            </label>

            <!-- Highlight Color -->
            <label title="Highlight Color" class="relative flex cursor-pointer items-center rounded p-1 transition-colors hover:bg-accent">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11-6 6v3h9l3-3"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>
                <input
                    type="color"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    :value="editor?.getAttributes('highlight').color ?? '#ffff00'"
                    @input="(e) => editor?.chain().focus().setHighlight({ color: (e.target as HTMLInputElement).value }).run()"
                />
            </label>

            <div class="mx-1 h-5 w-px bg-border" />

            <!-- Align Left -->
            <button
                type="button"
                title="Align Left"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive({ textAlign: 'left' }) }"
                @click="editor?.chain().focus().setTextAlign('left').run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
            </button>

            <!-- Align Center -->
            <button
                type="button"
                title="Align Center"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive({ textAlign: 'center' }) }"
                @click="editor?.chain().focus().setTextAlign('center').run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
            </button>

            <!-- Align Right -->
            <button
                type="button"
                title="Align Right"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive({ textAlign: 'right' }) }"
                @click="editor?.chain().focus().setTextAlign('right').run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/></svg>
            </button>

            <!-- Align Justify -->
            <button
                type="button"
                title="Justify"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive({ textAlign: 'justify' }) }"
                @click="editor?.chain().focus().setTextAlign('justify').run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

            <div class="mx-1 h-5 w-px bg-border" />

            <!-- Bullet List -->
            <button
                type="button"
                title="Bullet List"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('bulletList') }"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1" fill="currentColor" stroke="none"/></svg>
            </button>

            <!-- Ordered List -->
            <button
                type="button"
                title="Ordered List"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('orderedList') }"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
            </button>

            <!-- Task List -->
            <button
                type="button"
                title="Task List"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('taskList') }"
                @click="editor?.chain().focus().toggleTaskList().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="6" height="6" rx="1"/><path d="m3 17 2 2 4-4"/><line x1="13" y1="8" x2="21" y2="8"/><line x1="13" y1="18" x2="21" y2="18"/></svg>
            </button>

            <!-- Indent -->
            <button
                type="button"
                title="Indent"
                class="rounded p-1 transition-colors hover:bg-accent"
                @click="editor?.chain().focus().sinkListItem('listItem').run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="8" x2="21" y2="8"/><line x1="3" y1="16" x2="21" y2="16"/><polyline points="7 12 11 12"/><polyline points="9 10 11 12 9 14"/></svg>
            </button>

            <!-- Outdent -->
            <button
                type="button"
                title="Outdent"
                class="rounded p-1 transition-colors hover:bg-accent"
                @click="editor?.chain().focus().liftListItem('listItem').run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="8" x2="21" y2="8"/><line x1="3" y1="16" x2="21" y2="16"/><polyline points="13 12 9 12"/><polyline points="11 10 9 12 11 14"/></svg>
            </button>

            <div class="mx-1 h-5 w-px bg-border" />

            <!-- Blockquote -->
            <button
                type="button"
                title="Blockquote"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('blockquote') }"
                @click="editor?.chain().focus().toggleBlockquote().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
            </button>

            <!-- Code Block -->
            <button
                type="button"
                title="Code Block"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('codeBlock') }"
                @click="editor?.chain().focus().toggleCodeBlock().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="12" y1="2" x2="12" y2="22" stroke-opacity="0.3"/></svg>
            </button>

            <!-- Horizontal Rule -->
            <button
                type="button"
                title="Horizontal Rule"
                class="rounded p-1 transition-colors hover:bg-accent"
                @click="editor?.chain().focus().setHorizontalRule().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/></svg>
            </button>

            <!-- Hard Break -->
            <button
                type="button"
                title="Hard Break"
                class="rounded p-1 transition-colors hover:bg-accent"
                @click="editor?.chain().focus().setHardBreak().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7V12a5 5 0 0 1-5 5H5"/><polyline points="9 22 5 17 9 12"/></svg>
            </button>

            <!-- Link -->
            <button
                type="button"
                title="Insert Link"
                class="rounded p-1 transition-colors hover:bg-accent"
                :class="{ 'bg-accent': editor?.isActive('link') }"
                @click="openLinkInput"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </button>

            <!-- Unlink -->
            <button
                v-if="editor?.isActive('link')"
                type="button"
                title="Remove Link"
                class="rounded p-1 transition-colors hover:bg-accent"
                @click="editor?.chain().focus().unsetLink().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18.84 12.25 1.72-1.71h-.02a5.004 5.004 0 0 0-.12-7.07 5.006 5.006 0 0 0-6.95 0l-1.72 1.71"/><path d="m5.17 11.75-1.71 1.71a5.004 5.004 0 0 0 .12 7.07 5.006 5.006 0 0 0 6.95 0l1.71-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
            </button>

            <div class="mx-1 h-5 w-px bg-border" />

            <!-- Clear Formatting -->
            <button
                type="button"
                title="Clear Formatting"
                class="rounded p-1 transition-colors hover:bg-accent"
                @click="editor?.chain().focus().clearNodes().unsetAllMarks().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M5 20h6"/><path d="M13 4 8 20"/><line x1="15" y1="9" x2="21" y2="15"/><line x1="21" y1="9" x2="15" y2="15"/></svg>
            </button>

            <div class="mx-1 h-5 w-px bg-border" />

            <!-- Undo -->
            <button
                type="button"
                title="Undo (Ctrl+Z)"
                class="rounded p-1 transition-colors hover:bg-accent disabled:opacity-30"
                :disabled="!editor?.can().undo()"
                @click="editor?.chain().focus().undo().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
            </button>

            <!-- Redo -->
            <button
                type="button"
                title="Redo (Ctrl+Shift+Z)"
                class="rounded p-1 transition-colors hover:bg-accent disabled:opacity-30"
                :disabled="!editor?.can().redo()"
                @click="editor?.chain().focus().redo().run()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5v0A5.5 5.5 0 0 0 9.5 20H13"/></svg>
            </button>
        </div>

        <!-- Link input -->
        <div v-if="showLinkInput" class="flex items-center gap-2 border-b border-input bg-muted/40 px-3 py-2">
            <input
                v-model="linkUrl"
                type="url"
                placeholder="https://example.com"
                class="flex-1 rounded border border-input bg-background px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                @keydown.enter.prevent="applyLink"
                @keydown.escape.prevent="showLinkInput = false"
            />
            <button type="button" class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground" @click="applyLink">Apply</button>
            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-accent" @click="showLinkInput = false">Cancel</button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: hsl(var(--muted-foreground));
    pointer-events: none;
    height: 0;
}

.tiptap ul[data-type="taskList"] {
    list-style: none;
    padding: 0;
}

.tiptap ul[data-type="taskList"] li {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

.tiptap ul[data-type="taskList"] li > label {
    flex-shrink: 0;
    margin-top: 0.15rem;
}
</style>
