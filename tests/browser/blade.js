/**
 * Renders the subset of Blade the package views use (@json, {{ }}, @if with
 * @else, @foreach, @php blocks and comments) from a map of expression text
 * to value, so the browser suite runs the real view files. An expression the
 * map does not know throws, so a new directive in a view cannot silently
 * render as nothing.
 */
function render(blade, values) {
    const source = blade
        .replace(/\{\{--[\s\S]*?--\}\}/g, '')
        .replace(/@php[\s\S]*?@endphp/g, '');

    const tokens = tokenize(source);
    let position = 0;

    function lookup(expression, scope) {
        const key = expression.trim();

        if (key in scope) {
            return scope[key];
        }

        throw new Error('unknown Blade expression in view: ' + key);
    }

    function inline(text, scope) {
        return text
            .replace(/@json\((.*?)\)/g, (match, expression) => JSON.stringify(lookup(expression, scope)))
            .replace(/\{\{(.*?)\}\}/g, (match, expression) => String(lookup(expression, scope)));
    }

    function block(scope, stopAt) {
        let out = '';

        while (position < tokens.length) {
            const token = tokens[position];

            if (stopAt.includes(token.type)) {
                return out;
            }

            position++;

            if (token.type === 'text') {
                out += inline(token.text, scope);
            } else if (token.type === 'if') {
                const yes = block(scope, ['else', 'endif']);
                let no = '';

                if (tokens[position].type === 'else') {
                    position++;
                    no = block(scope, ['endif']);
                }

                position++;
                out += lookup(token.argument, scope) ? yes : no;
            } else if (token.type === 'foreach') {
                const match = token.argument.match(/^(\$\w+)\s+as\s+(\$\w+)$/);
                const items = lookup(match[1], scope);
                const start = position;

                for (const item of items) {
                    position = start;
                    out += block({ ...scope, [match[2]]: item }, ['endforeach']);
                }

                if (!items.length) {
                    block(scope, ['endforeach']);
                }

                position++;
            } else {
                throw new Error('unexpected @' + token.type);
            }
        }

        return out;
    }

    const html = block(values, []);
    const left = html.match(/@\w+|\{\{/);

    if (left) {
        throw new Error('unresolved Blade directive in view: ' + left[0]);
    }

    return html;
}

function tokenize(source) {
    const tokens = [];
    const pattern = /@(if|else|endif|foreach|endforeach)\b(?:\s*\((.*)\))?/g;
    let last = 0;
    let match;

    while ((match = pattern.exec(source))) {
        tokens.push({ type: 'text', text: source.slice(last, match.index) });
        tokens.push({ type: match[1], argument: match[2] });
        last = match.index + match[0].length;
    }

    tokens.push({ type: 'text', text: source.slice(last) });

    return tokens;
}

/** The content of every <script> block in a rendered view, joined. */
function scripts(html) {
    return [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]).join('\n');
}

module.exports = { render, scripts };
