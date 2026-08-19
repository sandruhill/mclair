import React from 'react';
import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig, Easing } from 'remotion';

// Brand tokens, matching the Painel Mclair manual artifact.
const RED = '#C8102E';
const GROUND = '#F1EBDD';
const GROUND_RAISED = '#E7DFC9';
const INK = '#211B14';
const INK_SOFT = '#665D4D';
const LINE = '#D6C9A8';

const SIDEBAR_ITEMS = ['Páginas', 'Serviços', 'Cases', 'Blog', 'Configurações', 'SEO & GEO'];
const ENTRIES = ['Homepage', 'Sobre', 'Clientes', 'Contato', 'Mentorias'];

// Phase boundaries (frames, at 30fps / 150 total = 5s)
const PHASE = {
  sidebarIdle: [0, 35],
  sidebarClick: [35, 45],
  entriesIn: [45, 60],
  entriesIdle: [60, 90],
  entryClick: [90, 100],
  panelIn: [100, 120],
  panelIdle: [120, 150],
} as const;

function easeFrame(frame: number, range: readonly [number, number]) {
  return interpolate(frame, range, [0, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: Easing.bezier(0.22, 1, 0.36, 1),
  });
}

const Cursor: React.FC<{ x: number; y: number; clicking: boolean }> = ({ x, y, clicking }) => (
  <div
    style={{
      position: 'absolute',
      left: x,
      top: y,
      width: 22,
      height: 22,
      pointerEvents: 'none',
      transform: `translate(-4px, -2px) scale(${clicking ? 0.82 : 1})`,
      transition: 'none',
    }}
  >
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
      <path d="M2 2L18 9L10.5 11L8 18.5L2 2Z" fill={INK} stroke={GROUND} strokeWidth="1.2" strokeLinejoin="round" />
    </svg>
    {clicking && (
      <div
        style={{
          position: 'absolute',
          left: -14,
          top: -14,
          width: 40,
          height: 40,
          borderRadius: '50%',
          border: `2px solid ${RED}`,
          opacity: 0.6,
        }}
      />
    )}
  </div>
);

export const PainelFlow: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  // ---- Sidebar click choreography ----
  const clickProgress = spring({ frame: frame - PHASE.sidebarClick[0], fps, config: { damping: 14, mass: 0.4 } });
  const cursorX = interpolate(frame, [0, PHASE.sidebarClick[0]], [420, 90], { extrapolateRight: 'clamp' });
  const cursorY = interpolate(frame, [0, PHASE.sidebarClick[0]], [260, 96], { extrapolateRight: 'clamp' });
  const isClickingSidebar = frame >= PHASE.sidebarClick[0] && frame < PHASE.sidebarClick[0] + 8;

  // ---- Entries list appears, cursor moves to "Sobre" ----
  const entriesOpacity = easeFrame(frame, PHASE.entriesIn);
  const entriesY = interpolate(easeFrame(frame, PHASE.entriesIn), [0, 1], [24, 0]);
  const cursor2X = interpolate(frame, [PHASE.entriesIdle[0], PHASE.entryClick[0]], [700, 260], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });
  const cursor2Y = interpolate(frame, [PHASE.entriesIdle[0], PHASE.entryClick[0]], [500, 214], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });
  const isClickingEntry = frame >= PHASE.entryClick[0] && frame < PHASE.entryClick[0] + 8;

  // ---- Panel with fields + preview slides in ----
  const panelOpacity = easeFrame(frame, PHASE.panelIn);
  const panelX = interpolate(easeFrame(frame, PHASE.panelIn), [0, 1], [40, 0]);
  const showList = frame < PHASE.panelIn[0] + 4;

  return (
    <AbsoluteFill style={{ background: GROUND, fontFamily: '-apple-system, BlinkMacSystemFont, sans-serif' }}>
      {/* Top bar */}
      <div
        style={{
          height: 56,
          borderBottom: `1px solid ${LINE}`,
          display: 'flex',
          alignItems: 'center',
          padding: '0 24px',
          gap: 10,
          background: '#fff',
        }}
      >
        <div style={{ width: 26, height: 26, borderRadius: 6, background: RED }} />
        <div style={{ fontWeight: 800, fontSize: 15, color: INK }}>Painel Mclair</div>
      </div>

      <div style={{ display: 'flex', flex: 1 }}>
        {/* Sidebar */}
        <div style={{ width: 220, borderRight: `1px solid ${LINE}`, background: '#fff', padding: '20px 12px' }}>
          <div style={{ fontSize: 11, letterSpacing: '0.08em', textTransform: 'uppercase', color: INK_SOFT, padding: '0 8px 10px' }}>
            Collections
          </div>
          {SIDEBAR_ITEMS.map((item, i) => {
            const active = item === 'Páginas' && frame >= PHASE.sidebarClick[0];
            return (
              <div
                key={item}
                style={{
                  padding: '10px 10px',
                  borderRadius: 6,
                  fontSize: 14.5,
                  fontWeight: active ? 700 : 500,
                  color: active ? RED : INK,
                  background: active ? GROUND_RAISED : 'transparent',
                  marginBottom: 2,
                }}
              >
                {item}
              </div>
            );
          })}
        </div>

        {/* Main content */}
        <div style={{ flex: 1, position: 'relative', padding: 28, overflow: 'hidden' }}>
          {showList && (
            <div style={{ opacity: entriesOpacity, transform: `translateY(${entriesY}px)` }}>
              <div style={{ fontSize: 22, fontWeight: 800, color: INK, marginBottom: 18 }}>Páginas</div>
              {ENTRIES.map((entry) => {
                const isSobre = entry === 'Sobre';
                const highlighted = isSobre && frame >= PHASE.entryClick[0] + 6;
                return (
                  <div
                    key={entry}
                    style={{
                      padding: '16px 4px',
                      borderBottom: `1px solid ${LINE}`,
                      fontSize: 16,
                      color: INK,
                      background: highlighted ? GROUND_RAISED : 'transparent',
                      borderRadius: highlighted ? 6 : 0,
                    }}
                  >
                    {entry}
                  </div>
                );
              })}
            </div>
          )}

          {frame >= PHASE.panelIn[0] && (
            <div
              style={{
                position: 'absolute',
                inset: 28,
                opacity: panelOpacity,
                transform: `translateX(${panelX}px)`,
                display: 'flex',
                gap: 20,
              }}
            >
              <div style={{ flex: 1, background: '#fff', border: `1px solid ${LINE}`, borderRadius: 10, padding: 20 }}>
                <div style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', color: INK_SOFT, marginBottom: 14 }}>
                  EDIT
                </div>
                {['Título da página', 'Meta description', 'Keywords'].map((label, i) => {
                  const fieldIn = easeFrame(frame, [PHASE.panelIn[0] + 8 + i * 6, PHASE.panelIn[0] + 20 + i * 6]);
                  return (
                    <div key={label} style={{ marginBottom: 16, opacity: fieldIn, transform: `translateY(${(1 - fieldIn) * 10}px)` }}>
                      <div style={{ fontSize: 12, fontWeight: 700, color: INK, marginBottom: 6 }}>{label}</div>
                      <div style={{ height: 34, border: `1px solid ${LINE}`, borderRadius: 6, background: GROUND }} />
                    </div>
                  );
                })}
              </div>
              <div style={{ flex: 1, background: GROUND_RAISED, border: `1px solid ${LINE}`, borderRadius: 10, padding: 20 }}>
                <div style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', color: INK_SOFT, marginBottom: 14 }}>
                  PREVIEW
                </div>
                <div style={{ fontSize: 20, fontWeight: 800, color: INK, marginBottom: 8 }}>Sobre a Mclair</div>
                <div style={{ fontSize: 13, color: INK_SOFT, lineHeight: 1.5 }}>
                  Comunicação Estratégica desde 2017.
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      <Cursor x={cursorX} y={cursorY} clicking={isClickingSidebar} />
      {frame >= PHASE.entriesIdle[0] && frame < PHASE.panelIn[0] && (
        <Cursor x={cursor2X} y={cursor2Y} clicking={isClickingEntry} />
      )}
    </AbsoluteFill>
  );
};
