// node --test "resources/js/tools/website-seo-checker/tests/*.test.mjs"
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { countLabel, ctaFor, reportFileName, reportText, scoreBand } from '../src/report.js';

const finding = (severity, category, title, extra = {}) => ({
    id: `${category}.x`, category, severity, title,
    evidence: [`evidence for ${title}`], why: 'Why.', fix: 'Fix it.', limitation: null, weight: 1, ...extra,
});

const report = (findings, extra = {}) => ({
    status: 'complete',
    audited_at: '2026-10-09T10:00:00+00:00',
    intent: 'live',
    submitted_url: 'http://example.com/',
    final_url: 'https://example.com/',
    http: { status: 200, response_ms: 320, redirects: [{ url: 'http://example.com/', status: 301 }] },
    score: 72,
    readiness: { level: 'blocked', label: 'Not ready to launch', summary: '1 launch blocker found.' },
    counts: { critical: 0, warning: 0, passed: 0, not_checked: 0 },
    categories: [
        { key: 'indexability', label: 'Indexability & crawling' },
        { key: 'metadata', label: 'Metadata' },
        { key: 'performance', label: 'Performance & mobile' },
    ],
    findings,
    platform: null,
    ...extra,
});

test('score bands carry no exact score', () => {
    assert.equal(scoreBand(null), 'none');
    assert.equal(scoreBand(100), '90-100');
    assert.equal(scoreBand(80), '75-89');
    assert.equal(scoreBand(59), '50-74');
    assert.equal(scoreBand(10), '0-49');
});

test('the CTA follows what was found', () => {
    assert.equal(ctaFor(report([finding('critical', 'indexability', 'noindex')])).key, 'pre-launch-review');
    assert.equal(ctaFor(report([finding('warning', 'performance', 'slow')], { platform: 'wordpress' })).key, 'wordpress-optimization');
    assert.match(ctaFor(report([finding('warning', 'structure', 'alt')], { platform: 'elementor' })).label, /Elementor/);
    assert.equal(ctaFor(report([finding('warning', 'social', 'og')])).key, 'seo-implementation');
    assert.equal(ctaFor(report([finding('warning', 'indexability', 'sitemap')])).key, 'technical-seo');
    assert.equal(ctaFor(report([finding('passed', 'metadata', 'title')])).key, 'launch-project');
});

test('the text report lists blockers first with evidence and fixes, and passes untouched text through', () => {
    const text = reportText(report([
        finding('passed', 'metadata', 'Title set', { evidence: ['Title: "<script>x</script>"'] }),
        finding('critical', 'indexability', 'noindex found', { limitation: 'Not index status.' }),
        finding('warning', 'metadata', 'No description'),
        finding('not_checked', 'performance', 'Core Web Vitals'),
    ]), { dateText: '9 Oct 2026' });

    assert.match(text, /^Website SEO\/Metadata Pre-launch Checker — report/);
    assert.match(text, /Page checked: https:\/\/example\.com\//);
    assert.match(text, /Address entered: http:\/\/example\.com\//);
    assert.match(text, /Checklist score: 72\/100 \(not a Google score\)/);
    assert.ok(text.indexOf('LAUNCH BLOCKERS (1)') < text.indexOf('WARNINGS (1)'));
    assert.ok(text.indexOf('WARNINGS (1)') < text.indexOf('PASSED CHECKS (1)'));
    assert.ok(text.indexOf('PASSED CHECKS (1)') < text.indexOf('NOT CHECKED (1)'));
    assert.match(text, /1\. \[Indexability & crawling\] noindex found\n {3}Found: evidence for noindex found\n {3}Why it matters: Why\.\n {3}How to fix: Fix it\.\n {3}Note: Not index status\./);
    assert.match(text, /- \[Metadata\] Title set — Title: "<script>x<\/script>"/);
    assert.match(text, /Core Web Vitals\) was not measured/);
});

test('an unscored or unavailable report says so', () => {
    assert.match(reportText(report([], { score: null })), /Checklist score: not scored/);
    const unavailable = reportText({ status: 'unavailable', intent: 'staging', submitted_url: 'https://dev.example.com/', audited_at: 'x', error: { message: 'Timed out.' } });
    assert.match(unavailable, /Checked as: a staging or pre-launch copy/);
    assert.match(unavailable, /couldn't be completed: Timed out\./);
});

test('download file names use only the host', () => {
    assert.equal(reportFileName(report([]), new Date('2026-10-09T12:00:00Z')), 'seo-pre-launch-report-example.com-2026-10-09.txt');
    assert.equal(reportFileName({ final_url: 'not a url' }, new Date('2026-10-09T12:00:00Z')), 'seo-pre-launch-report-website-2026-10-09.txt');
});

test('count chips use the right plural', () => {
    assert.equal(countLabel(1, 'warning'), '1 warning');
    assert.equal(countLabel(2, 'warning'), '2 warnings');
    assert.equal(countLabel(0, 'critical'), '0 launch blockers');
    assert.equal(countLabel(1, 'critical'), '1 launch blocker');
    assert.equal(countLabel(33, 'passed'), '33 passed checks');
    assert.equal(countLabel(1, 'not_checked'), '1 not checked');
});
