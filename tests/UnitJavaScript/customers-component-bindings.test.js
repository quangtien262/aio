import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { transformSync } from '@babel/core';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';
import esbuild from 'esbuild';

const root = path.resolve('resources/admin/src/modules/customers');
const globals = new Set(['window', 'document', 'navigator', 'console', 'globalThis', 'JSON', 'String', 'Object', 'Number', 'Boolean', 'BigInt', 'Date', 'Error', 'Promise', 'Set', 'Map', 'URL', 'URLSearchParams', 'TextEncoder', 'Uint8Array', 'CustomEvent', 'undefined', 'encodeURIComponent']);
const files = (directory) => fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? files(target) : target.endsWith('.jsx') ? [target] : [];
});
for (const filename of files(root)) {
    test(`${path.basename(filename)} has no unbound runtime references after Babel and JSX transforms`, () => {
        const { code } = transformSync(fs.readFileSync(filename, 'utf8'), { filename, parserOpts: { plugins: ['jsx'] }, plugins: [['babel-plugin-import', { libraryName: 'antd', libraryDirectory: 'es', style: false }, 'antd']] });
        const output = esbuild.transformSync(code, { loader: 'jsx', jsx: 'automatic' }).code;
        const missing = new Set();
        traverseModule.default(parse(output, { sourceType: 'module' }), { ReferencedIdentifier(reference) { const name = reference.node.name; if (!reference.scope.hasBinding(name) && !globals.has(name)) missing.add(name); } });
        assert.deepEqual([...missing], [], `Undefined references in ${filename}`);
    });
}
