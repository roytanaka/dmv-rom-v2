/**
 * The file-type icon for a Document (#777, spec #773): a Phosphor icon picked by MIME type,
 * so a Member can see a file's type at a glance. A link Document gets the link icon. Used by
 * the Document library rows and the viewer's file cards (#781).
 */
import {
    PhFile,
    PhFileAudio,
    PhFileCsv,
    PhFileDoc,
    PhFileImage,
    PhFilePdf,
    PhFilePpt,
    PhFileSvg,
    PhFileTxt,
    PhFileVideo,
    PhFileXls,
    PhFileZip,
    PhLink,
} from '@phosphor-icons/vue';
import type { Component } from 'vue';

const exact: Record<string, Component> = {
    'application/pdf': PhFilePdf,

    'application/zip': PhFileZip,
    'application/x-zip-compressed': PhFileZip,
    'application/x-7z-compressed': PhFileZip,
    'application/x-rar-compressed': PhFileZip,
    'application/vnd.rar': PhFileZip,
    'application/gzip': PhFileZip,
    'application/x-tar': PhFileZip,

    'application/msword': PhFileDoc,
    'application/rtf': PhFileDoc,
    'application/vnd.oasis.opendocument.text': PhFileDoc,

    'application/vnd.ms-excel': PhFileXls,
    'application/vnd.oasis.opendocument.spreadsheet': PhFileXls,

    'application/vnd.ms-powerpoint': PhFilePpt,
    'application/vnd.oasis.opendocument.presentation': PhFilePpt,

    'text/csv': PhFileCsv,
    'text/plain': PhFileTxt,
    'image/svg+xml': PhFileSvg,
};

// The Office Open XML types, by their family prefix (a .docx, a .dotx and so on).
const prefixes: [string, Component][] = [
    ['application/vnd.openxmlformats-officedocument.wordprocessingml.', PhFileDoc],
    ['application/vnd.openxmlformats-officedocument.spreadsheetml.', PhFileXls],
    ['application/vnd.openxmlformats-officedocument.presentationml.', PhFilePpt],
    ['image/', PhFileImage],
    ['audio/', PhFileAudio],
    ['video/', PhFileVideo],
];

export function fileTypeIcon(document: { kind: 'file' | 'link'; mimeType: string | null }): Component {
    if (document.kind === 'link') return PhLink;

    const mime = (document.mimeType ?? '').split(';')[0].trim().toLowerCase();

    return exact[mime] ?? prefixes.find(([prefix]) => mime.startsWith(prefix))?.[1] ?? PhFile;
}
