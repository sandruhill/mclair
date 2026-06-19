#!/usr/bin/env python3
"""Migrate 265 posts from src/data/blog.json to Keystatic .mdoc format."""
import json, os, re, sys
from html.parser import HTMLParser

# ── HTML → Markdown converter ──────────────────────────────────────────────

class HtmlToMd(HTMLParser):
    def __init__(self):
        super().__init__()
        self.result = []
        self._stack = []          # tag stack
        self._list_type = []      # 'ul' or 'ol'
        self._list_counter = []   # counters for ol
        self._skip = 0            # skip contents of certain tags
        self._current_href = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        self._stack.append(tag)

        if tag in ('script', 'style', 'head', 'nav', 'table', 'thead', 'tbody', 'tr', 'td', 'th'):
            self._skip += 1
            return

        if self._skip:
            return

        if tag == 'p':
            if self.result and self.result[-1] != '\n\n':
                self.result.append('\n\n')
        elif tag in ('h1', 'h2', 'h3', 'h4', 'h5', 'h6'):
            n = int(tag[1])
            self.result.append('\n\n' + '#' * n + ' ')
        elif tag in ('strong', 'b'):
            self.result.append('**')
        elif tag in ('em', 'i'):
            self.result.append('*')
        elif tag == 'a':
            self._current_href = attrs.get('href', '')
            self.result.append('[')
        elif tag == 'br':
            self.result.append('  \n')
        elif tag in ('ul', 'ol'):
            self._list_type.append(tag)
            self._list_counter.append(0)
            self.result.append('\n')
        elif tag == 'li':
            if self._list_type and self._list_type[-1] == 'ol':
                self._list_counter[-1] += 1
                self.result.append(f'\n{self._list_counter[-1]}. ')
            else:
                self.result.append('\n- ')
        elif tag == 'blockquote':
            self.result.append('\n> ')
        elif tag == 'pre':
            self.result.append('\n\n```\n')
        elif tag == 'code' and self._stack.count('pre') == 0:
            self.result.append('`')
        elif tag == 'img':
            src = attrs.get('src', '')
            alt = attrs.get('alt', '')
            if src:
                self.result.append(f'\n\n![{alt}]({src})\n\n')
        elif tag == 'hr':
            self.result.append('\n\n---\n\n')

    def handle_endtag(self, tag):
        if tag in ('script', 'style', 'head', 'nav', 'table', 'thead', 'tbody', 'tr', 'td', 'th'):
            self._skip = max(0, self._skip - 1)

        if self._stack and self._stack[-1] == tag:
            self._stack.pop()

        if self._skip:
            return

        if tag == 'p':
            self.result.append('\n\n')
        elif tag in ('h1', 'h2', 'h3', 'h4', 'h5', 'h6'):
            self.result.append('\n\n')
        elif tag in ('strong', 'b'):
            self.result.append('**')
        elif tag in ('em', 'i'):
            self.result.append('*')
        elif tag == 'a':
            href = self._current_href or ''
            self.result.append(f']({href})')
            self._current_href = None
        elif tag in ('ul', 'ol'):
            if self._list_type:
                self._list_type.pop()
            if self._list_counter:
                self._list_counter.pop()
            self.result.append('\n')
        elif tag == 'pre':
            self.result.append('\n```\n\n')
        elif tag == 'code' and 'pre' not in self._stack:
            self.result.append('`')
        elif tag == 'blockquote':
            self.result.append('\n\n')

    def handle_data(self, data):
        if self._skip:
            return
        self.result.append(data)

    def get_markdown(self):
        md = ''.join(self.result)
        # Collapse 3+ blank lines to 2
        md = re.sub(r'\n{3,}', '\n\n', md)
        return md.strip()


def html_to_md(html: str) -> str:
    parser = HtmlToMd()
    parser.feed(html)
    return parser.get_markdown()


# ── Date conversion: DD/MM/YYYY → YYYY-MM-DD ──────────────────────────────

def parse_date(raw: str) -> str:
    raw = raw.strip()
    # Try DD/MM/YYYY
    m = re.match(r'^(\d{1,2})/(\d{1,2})/(\d{4})$', raw)
    if m:
        d, mo, y = m.groups()
        return f"{y}-{mo.zfill(2)}-{d.zfill(2)}"
    # Try YYYY-MM-DD already
    if re.match(r'^\d{4}-\d{2}-\d{2}$', raw):
        return raw
    return raw


# ── YAML value escaping ────────────────────────────────────────────────────

def yaml_str(v: str) -> str:
    """Wrap in double quotes, escaping internal quotes and backslashes."""
    if not v:
        return '""'
    v = v.replace('\\', '\\\\').replace('"', '\\"')
    return f'"{v}"'


# ── Main migration ─────────────────────────────────────────────────────────

def main():
    base = os.path.dirname(os.path.abspath(__file__))
    src = os.path.join(base, 'src', 'data', 'blog.json')
    out_dir = os.path.join(base, 'src', 'content', 'blog')
    os.makedirs(out_dir, exist_ok=True)

    with open(src, encoding='utf-8') as f:
        posts = json.load(f)

    created = 0
    skipped = 0
    for post in posts:
        slug = post['slug']
        dest = os.path.join(out_dir, f'{slug}.mdoc')

        # Don't overwrite existing CMS posts
        if os.path.exists(dest):
            skipped += 1
            continue

        title   = post.get('title', '')
        subtitle = post.get('subtitle', '') or ''
        date    = parse_date(post.get('date', ''))
        author  = post.get('author', '') or 'Equipe Mclair'
        image   = post.get('image', '') or ''
        content_html = post.get('content', '') or ''
        content_md = html_to_md(content_html)

        frontmatter = f"""---
title: {yaml_str(title)}
subtitle: {yaml_str(subtitle)}
metaDescription: ""
date: {date}
author: {yaml_str(author)}
image: {yaml_str(image)}
category: "Geral"
keywords: []
aboutTopics: []
faqItems: []
---
"""
        with open(dest, 'w', encoding='utf-8') as f:
            f.write(frontmatter + '\n' + content_md + '\n')

        created += 1
        if created % 50 == 0:
            print(f'  {created} posts migrated...')

    print(f'\nDone: {created} created, {skipped} skipped (already existed).')


if __name__ == '__main__':
    main()
