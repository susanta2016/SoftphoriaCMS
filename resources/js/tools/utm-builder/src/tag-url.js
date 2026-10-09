// Adds UTM parameters to a parsed destination URL without rewriting the rest
// of it. URLSearchParams would re-serialize every existing parameter (%20
// becomes +, a bare "flag" becomes "flag=", ";" and "/" get escaped, invalid
// UTF-8 is replaced), so the query string is edited as text instead:
//
// - every existing parameter stays exactly as the URL parser left it,
// - a UTM key is removed (all copies) only when its field has a value, and the
//   new value is appended once, encoded with encodeURIComponent,
// - a blank field leaves any value already in the destination alone,
// - the #fragment and everything before the query are untouched.

export const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'];

// A key as an analytics tool reads it ("+" is a space); a malformed escape
// is compared as written.
const decodeKey = (raw) => {
    const key = raw.replace(/\+/g, ' ');
    try {
        return decodeURIComponent(key);
    } catch {
        return key;
    }
};

/**
 * @param {URL} url  the destination, already validated
 * @param {Record<string, string>} values  cleaned field values by UTM key
 * @returns {string}
 */
export function tagUrl(url, values) {
    const supplied = UTM_KEYS.filter((key) => values[key]);

    const kept = url.search
        .slice(1)
        .split('&')
        .filter((pair) => pair !== '' && !supplied.includes(decodeKey(pair.split('=', 1)[0])));
    const added = supplied.map((key) => `${key}=${encodeURIComponent(values[key])}`);

    const tagged = new URL(url.href);
    tagged.search = [...kept, ...added].join('&');

    return tagged.href;
}
