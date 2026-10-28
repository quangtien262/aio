import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { transformSync } from '@babel/core';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';
import esbuild from 'esbuild';

const root = path.resolve('resources/admin/src/modules/pharmacy');
const globals = new Set(['window', 'document', 'globalThis', 'JSON', 'String', 'Object', 'Number', 'Boolean', 'BigInt', 'Date', 'Error', 'Promise', 'Set', 'Map', 'URLSearchParams', 'TextEncoder', 'Uint8Array', 'Intl', 'undefined', 'encodeURIComponent']);
const files = (directory) => fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? files(target) : target.endsWith('.jsx') ? [target] : [];
});
for (const filename of files(root)) test(`${path.relative(root, filename)} retains runtime bindings after Vite transforms`, () => {
    const { code } = transformSync(fs.readFileSync(filename, 'utf8'), { filename, parserOpts: { plugins: ['jsx'] }, plugins: [['babel-plugin-import', { libraryName: 'antd', libraryDirectory: 'es', style: false }, 'antd']] });
    const output = esbuild.transformSync(code, { loader: 'jsx', jsx: 'automatic' }).code;
    const missing = new Set();
    traverseModule.default(parse(output, { sourceType: 'module' }), { ReferencedIdentifier(reference) {
        const name = reference.node.name;
        if (!reference.scope.hasBinding(name) && !globals.has(name)) missing.add(name);
    } });
    assert.deepEqual([...missing], []);
});
