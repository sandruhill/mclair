/**
 * Datas dos posts. O banco guarda "AAAA-MM-DD" (coluna antiga, DATE) ou
 * "AAAA-MM-DD HH:MM:SS" (DATETIME), sempre na hora de Brasília e sem fuso.
 * Os posts migrados do site antigo ainda podem vir como "DD/MM/AAAA".
 */
const FUSO = '-03:00'; // America/Sao_Paulo (sem horário de verão desde 2019)

export type DataPost = { ano: number; mes: number; dia: number; hora?: number; minuto?: number };

export function lerData(d: string | null | undefined): DataPost | null {
  if (!d) return null;
  let m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(d);
  if (m) {
    const r: DataPost = { ano: +m[1], mes: +m[2], dia: +m[3] };
    if (m[4]) { r.hora = +m[4]; r.minuto = +m[5]; }
    return r;
  }
  m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})/.exec(d);
  if (m) return { ano: +m[3], mes: +m[2], dia: +m[1] };
  return null;
}

const p2 = (n: number) => String(n).padStart(2, '0');

/** ISO 8601 para JSON-LD e meta tags: com hora e fuso quando o post tem hora; só a data quando não tem. */
export function isoComFuso(d: string | null | undefined): string {
  const x = lerData(d);
  if (!x) return '';
  const data = `${x.ano}-${p2(x.mes)}-${p2(x.dia)}`;
  return x.hora === undefined ? data : `${data}T${p2(x.hora)}:${p2(x.minuto ?? 0)}:00${FUSO}`;
}

const MESES_LONGOS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

/** "25 de setembro de 2026"; com { hora: true } e post com hora, "25 de setembro de 2026, 09h00". */
export function dataPorExtenso(d: string | null | undefined, opts: { hora?: boolean } = {}): string {
  const x = lerData(d);
  if (!x) return '';
  const base = `${x.dia} de ${MESES_LONGOS[x.mes - 1]} de ${x.ano}`;
  return opts.hora && x.hora !== undefined ? `${base}, ${p2(x.hora)}h${p2(x.minuto ?? 0)}` : base;
}

/** Ordenação: timestamp (hora de Brasília tratada como UTC, só para comparar). */
export function ordemDaData(d: string | null | undefined): number {
  const x = lerData(d);
  if (!x) return 0;
  return Date.UTC(x.ano, x.mes - 1, x.dia, x.hora ?? 0, x.minuto ?? 0);
}
