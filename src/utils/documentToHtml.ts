// Converts Keystatic `fields.document` nodes to an HTML string.
// Mirrors the same tags used in the existing blog.json-based pages.

type TextNode = {
  text: string
  bold?: boolean
  italic?: boolean
  underline?: boolean
  strikethrough?: boolean
  code?: boolean
  superscript?: boolean
  subscript?: boolean
  keyboard?: boolean
}

type LinkNode = {
  type: 'link'
  href: string
  children: InlineNode[]
}

type InlineNode = TextNode | LinkNode

type ListItemNode = {
  type: 'list-item' | 'list-item-content'
  children: InlineNode[]
}

type BlockNode =
  | { type: 'paragraph'; children: InlineNode[] }
  | { type: 'heading'; level: 1 | 2 | 3 | 4 | 5 | 6; children: InlineNode[] }
  | { type: 'ordered-list'; children: ListItemNode[] }
  | { type: 'unordered-list'; children: ListItemNode[] }
  | { type: 'blockquote'; children: BlockNode[] }
  | { type: 'code'; children: TextNode[] }
  | { type: 'divider' }
  | { type: string; children?: (BlockNode | InlineNode)[] }

function esc(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function escAttr(s: string): string {
  return s.replace(/"/g, '&quot;')
}

function renderInline(nodes: InlineNode[]): string {
  return (nodes ?? [])
    .map((node) => {
      if ('text' in node) {
        let t = esc(node.text ?? '')
        if (node.code)          t = `<code>${t}</code>`
        if (node.bold)          t = `<strong>${t}</strong>`
        if (node.italic)        t = `<em>${t}</em>`
        if (node.underline)     t = `<u>${t}</u>`
        if (node.strikethrough) t = `<s>${t}</s>`
        if (node.superscript)   t = `<sup>${t}</sup>`
        if (node.subscript)     t = `<sub>${t}</sub>`
        return t
      }
      if (node.type === 'link') {
        const href = escAttr(node.href ?? '')
        return `<a href="${href}" target="_blank" rel="noopener noreferrer">${renderInline(node.children ?? [])}</a>`
      }
      return ''
    })
    .join('')
}

function renderListItem(li: ListItemNode): string {
  // list-item may wrap a list-item-content node
  if (li.children?.[0] && 'type' in li.children[0] && (li.children[0] as any).type === 'list-item-content') {
    return renderInline((li.children[0] as any).children ?? [])
  }
  return renderInline(li.children as InlineNode[])
}

export function documentToHtml(nodes: BlockNode[]): string {
  return (nodes ?? [])
    .map((node) => {
      switch (node.type) {
        case 'paragraph':
          return `<p>${renderInline(node.children as InlineNode[])}</p>`

        case 'heading':
          return `<h${node.level}>${renderInline(node.children as InlineNode[])}</h${node.level}>`

        case 'unordered-list':
          return `<ul>${(node.children as ListItemNode[]).map((li) => `<li>${renderListItem(li)}</li>`).join('')}</ul>`

        case 'ordered-list':
          return `<ol>${(node.children as ListItemNode[]).map((li) => `<li>${renderListItem(li)}</li>`).join('')}</ol>`

        case 'blockquote':
          return `<blockquote>${documentToHtml(node.children as BlockNode[])}</blockquote>`

        case 'code':
          return `<pre><code>${(node.children as TextNode[]).map((n) => esc(n.text ?? '')).join('')}</code></pre>`

        case 'divider':
          return '<hr>'

        default:
          if (node.children) return documentToHtml(node.children as BlockNode[])
          return ''
      }
    })
    .join('\n')
}
