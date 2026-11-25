'use strict';

// Run with: node --test tests/js/*.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');
const { MANIFESTS, ROOT, build, filesIn, manifestVersion, addUpdate, addChangelog, changelogEntry, releaseChangelog,
  releaseNotes, setManifestVersion, zip, crc32, packageZipName } = require(path.join(__dirname, '../../tools/release.js'));

const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

// Reads a zip made by release.js back to { name: Buffer }.
function unzip(buf) {
  const out = {};
  let p = buf.readUInt32LE(buf.length - 6);
  for (let n = buf.readUInt16LE(buf.length - 12); n > 0; n--) {
    assert.equal(buf.readUInt32LE(p), 0x02014b50);
    const method = buf.readUInt16LE(p + 10);
    const size = buf.readUInt32LE(p + 20);
    const nameLen = buf.readUInt16LE(p + 28);
    const offset = buf.readUInt32LE(p + 42);
    const name = buf.toString('utf8', p + 46, p + 46 + nameLen);
    const start = offset + 30 + buf.readUInt16LE(offset + 26);
    const body = buf.subarray(start, start + size);
    out[name] = method ? zlib.inflateRawSync(body) : body;
    assert.equal(crc32(out[name]), buf.readUInt32LE(p + 16), `crc of ${name}`);
    p += 46 + nameLen;
  }
  return out;
}

test('crc32 matches the known value', () => {
  assert.equal(crc32(Buffer.from('123456789')), 0xcbf43926);
});

test('zip can be read back', () => {
  const data = Buffer.from('hello '.repeat(100));
  const files = unzip(zip([['a/b.txt', data], ['c.txt', Buffer.from('x')]]));
  assert.deepEqual(Object.keys(files), ['a/b.txt', 'c.txt']);
  assert.deepEqual(files['a/b.txt'], data);
});

test('prepare sets version and date in a manifest', () => {
  const xml = '<version>3.7.0</version>\n<creationDate>September 2026</creationDate>';
  assert.equal(setManifestVersion(xml, '4.0.0', 'November 2026'),
    '<version>4.0.0</version>\n<creationDate>November 2026</creationDate>');
});

test('all manifests have the same version', () => {
  const versions = Object.values(MANIFESTS).map((f) => manifestVersion(read(f)));
  assert.equal(new Set(versions).size, 1, versions.join(', '));
});

test('build makes one package with the three extensions', () => {
  const file = build();
  assert.equal(path.basename(file), packageZipName(manifestVersion(read(MANIFESTS.package))));
  const pkg = unzip(fs.readFileSync(file));
  assert.deepEqual(Object.keys(pkg).sort(), ['language/en-GB/pkg_cbmodulecontent.sys.ini', 'language/nl-NL/pkg_cbmodulecontent.sys.ini',
    'packages/lib_cblisthelper.zip', 'packages/mod_cblistmodule.zip', 'packages/plg_content_cbprofile.zip', 'pkg_cbmodulecontent.xml', 'script.php']);
  assert.match(read(MANIFESTS.package), /<scriptfile>script\.php<\/scriptfile>/);
  // Every zip the package manifest asks for is there.
  for (const [, zipName] of read(MANIFESTS.package).matchAll(/<file [^>]*>([^<]+)<\/file>/g)) {
    assert.ok(pkg[`packages/${zipName}`], zipName);
  }
  for (const ext of ['lib_cblisthelper', 'mod_cblistmodule', 'plg_content_cbprofile']) {
    const files = unzip(pkg[`packages/${ext}.zip`]);
    assert.deepEqual(Object.keys(files), filesIn(`src/${ext}`).map(([n]) => n));
    assert.ok(Object.keys(files).every((n) => !n.includes('\\')), 'forward slashes');
  }
  assert.ok(unzip(pkg['packages/plg_content_cbprofile.zip'])['tmpl/div-avatar.php']);
  assert.ok(unzip(pkg['packages/mod_cblistmodule.zip'])['services/provider.php']);
});

test('the Unreleased section becomes the release', () => {
  const log = 'Unreleased  \nFixed: a  \n\nv3.9.0 September 2026  \nNew: b  \n';
  const out = releaseChangelog(log, '4.0.0', 'November 2026');
  assert.match(out, /^v4\.0\.0 November 2026 {2}\nFixed: a/);
  assert.equal(releaseNotes(out, '4.0.0').split('\n')[0], '- Fixed: a');
  assert.doesNotMatch(releaseNotes(out, '4.0.0'), /New: b/);
});

test('the Older history footer is not part of the release notes', () => {
  const log = 'v4.0.0 November 2026  \nNew: a  \n\nOlder history: [module](x)\n';
  assert.doesNotMatch(releaseNotes(log, '4.0.0'), /Older history/);
  assert.doesNotMatch(changelogEntry(log, '4.0.0'), /Older history/);
});

test('a release without changelog entries is refused', () => {
  assert.throws(() => releaseChangelog('Unreleased  \n\nv3.9.0 x\nNew: b\n', '4.0.0', 'x'), /empty/);
  assert.throws(() => releaseChangelog('Unreleased  \n\nOlder history: x\n', '4.0.0', 'x'), /empty/);
  assert.throws(() => releaseChangelog('v3.9.0 x\nNew: b\n', '4.0.0', 'x'), /no "Unreleased"/);
});

test('update-xml adds the package once and keeps other versions', () => {
  const first = addUpdate('<?xml version="1.0" encoding="utf-8"?>\n<updates>\n</updates>\n', '4.0.0', 'ab'.repeat(32));
  const out = addUpdate(first, '4.1.0', 'cd'.repeat(32));
  for (const v of ['4.0.0', '4.1.0']) assert.equal(out.split(`<version>${v}</version>`).length - 1, 1);
  assert.match(out, /<element>pkg_cbmodulecontent<\/element>\s*<type>package<\/type>/);
  assert.match(out, /releases\/download\/v4\.1\.0\/pkg_cbmodulecontent-4\.1\.0\.zip/);
  assert.match(out, /<sha256>(cd){32}<\/sha256>/);
  assert.match(out, /<changelogurl>https:\/\/raw\.githubusercontent\.com\/Tazzios\/cbmodulecontent\/main\/changelog\.xml<\/changelogurl>/);
  // Running it again replaces the entry instead of adding it twice.
  assert.equal(addUpdate(out, '4.1.0', 'cd'.repeat(32)), out);
  assert.match(out, /<\/updates>\n$/);
});

test('changelog.xml gets the changes of a version, grouped by type', () => {
  const log = 'v4.1.0 December 2026  \nSecurity: a & b  \nFixed: c  \nNew: d  \nImproved: e  \nChanged: f  \nSomething else  \n\nv4.0.0 x\nFixed: old\n';
  const entry = changelogEntry(log, '4.1.0');
  assert.match(entry, /<element>pkg_cbmodulecontent<\/element>\s*<type>package<\/type>\s*<version>4\.1\.0<\/version>/);
  assert.match(entry, /<security>\s*<item>a &amp; b<\/item>/);
  assert.match(entry, /<fix>\s*<item>c<\/item>/);
  assert.match(entry, /<addition>\s*<item>d<\/item>/);
  assert.match(entry, /<change>\s*<item>e<\/item>\s*<item>f<\/item>/);
  assert.match(entry, /<note>\s*<item>Something else<\/item>/);
  assert.doesNotMatch(entry, /old/);

  const xml = addChangelog(addChangelog('', changelogEntry(log, '4.0.0'), '4.0.0'), entry, '4.1.0');
  assert.ok(xml.indexOf('4.1.0') < xml.indexOf('4.0.0'), 'newest first');
  assert.equal(addChangelog(xml, entry, '4.1.0'), xml, 'running twice changes nothing');
});

test('changelog.xml contains plain text, no markdown', () => {
  const log = 'v4.1.0 December 2026  \nFixed: thanks to [Magnus Hasselquist](https://github.com/magnushasselquist/) for `IN`  \nNew: **bold** and <https://example.com>  \n';
  const entry = changelogEntry(log, '4.1.0');
  assert.match(entry, /<item>thanks to Magnus Hasselquist for IN<\/item>/);
  assert.match(entry, /<item>bold and https:\/\/example\.com<\/item>/);
  assert.doesNotMatch(entry, /\]\(|`|\*\*/);
});

test('a breaking change is marked in the release notes and in changelog.xml', () => {
  const log = 'v6.0.0 x\nNew: a  \nBreaking: old functions are gone  \n';
  assert.match(changelogEntry(log, '6.0.0'), /<change>\s*<item>Breaking: old functions are gone<\/item>/);
  assert.match(releaseNotes(log, '6.0.0'), /^- Breaking: old functions are gone$/m);
});

test('only the package has an update server, and it points to updates.xml and changelog.xml', () => {
  for (const key of ['library', 'module', 'plugin']) assert.doesNotMatch(read(MANIFESTS[key]), /updateservers/);
  assert.match(read(MANIFESTS.package), /Tazzios\/cbmodulecontent\/main\/updates\.xml/);
  assert.match(read(MANIFESTS.package), /<changelogurl>https:\/\/raw\.githubusercontent\.com\/Tazzios\/cbmodulecontent\/main\/changelog\.xml<\/changelogurl>/);
  assert.match(read('changelog.xml'), /<changelogs>/);
});
