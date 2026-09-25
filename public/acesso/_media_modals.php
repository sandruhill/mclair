<?php
// Modais de mídia do editor de post (imagem, vídeo, galeria). A lógica está
// em editor-midia.js; aqui só a marcação e o CSS. Chamar cmsMediaModals()
// uma vez, depois do formulário do post.
function cmsMediaModals(): void {
    static $emitido = false;
    if ($emitido) return;
    $emitido = true;
?>
<style>
  /* Botões próprios na barra do editor: ícone de traço, no mesmo tamanho dos da biblioteca. */
  .toastui-editor-toolbar-icons.mm-btn { background-image:none; display:inline-flex; align-items:center; justify-content:center; color:#4a4c52; }
  .toastui-editor-toolbar-icons.mm-btn svg { width:18px; height:18px; }
  .toastui-editor-toolbar-icons.mm-btn:hover { color:var(--ink); }

  /* Modais */
  .mm { display:none; position:fixed; inset:0; background:rgba(26,27,30,.5); z-index:120; align-items:flex-start; justify-content:center; padding:24px 16px; overflow-y:auto; }
  .mm.open { display:flex; }
  .mm > .card { width:720px; max-width:100%; margin:auto; padding:0; }
  .mm-cabeca { display:flex; align-items:center; justify-content:space-between; padding:16px 22px 12px; border-bottom:1px solid var(--line); }
  .mm-cabeca h2 { margin:0; font-size:1.05rem; }
  .mm-cabeca button { background:none; border:none; cursor:pointer; font:inherit; font-size:.85rem; font-weight:700; color:var(--ink-3); padding:4px 6px; }
  .mm-corpo { padding:6px 22px 18px; }
  .mm-pe { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:14px 22px; border-top:1px solid var(--line); }
  .mm-erro { flex:1; font-size:.78rem; font-weight:600; color:var(--red); margin:0; }
  .mm-intro { font-size:.8rem; color:var(--ink-3); margin:10px 0 0; line-height:1.5; }
  .mm .hero-tabs { margin-top:12px; }

  /* Zona de arrastar */
  .mm-zona { margin-top:10px; border:1.5px dashed var(--line); border-radius:10px; background:var(--soft); padding:26px 16px; text-align:center; cursor:pointer; color:var(--ink-3); font-size:.85rem; line-height:1.5; }
  .mm-zona strong { display:block; color:var(--ink); font-size:.9rem; }
  .mm-zona.over { border-color:var(--red); background:rgba(200,16,46,.05); }
  .mm-progresso { height:6px; border-radius:3px; background:var(--soft); overflow:hidden; margin-top:10px; display:none; }
  .mm-progresso i { display:block; height:100%; width:0; background:var(--red); transition:width .15s linear; }
  .mm-progresso.on { display:block; }

  /* Prévia da imagem escolhida */
  .mm-previa { display:flex; gap:14px; align-items:flex-start; margin-top:12px; }
  .mm-previa img { width:160px; height:120px; object-fit:cover; border-radius:8px; background:var(--soft); flex-shrink:0; }
  .mm-previa .info { min-width:0; flex:1; font-size:.78rem; color:var(--ink-3); }

  /* Já enviadas (grade) */
  .mm-grade { display:grid; grid-template-columns:repeat(auto-fill, minmax(104px, 1fr)); gap:8px; margin-top:12px; max-height:300px; overflow:auto; }
  .mm-grade button { position:relative; border:2px solid transparent; border-radius:8px; padding:0; background:var(--soft); cursor:pointer; overflow:hidden; aspect-ratio:4/3; }
  .mm-grade button img { width:100%; height:100%; object-fit:cover; display:block; }
  .mm-grade button.on { border-color:var(--red); }
  .mm-grade button.on::after { content:""; position:absolute; top:6px; right:6px; width:18px; height:18px; border-radius:50%; background:var(--red); box-shadow:0 0 0 2px #fff; }

  /* Vídeo */
  .mm-video-previa { margin-top:12px; border-radius:10px; overflow:hidden; background:var(--ink); aspect-ratio:16/9; display:none; }
  .mm-video-previa.on { display:block; }
  .mm-video-previa.vertical { aspect-ratio:9/16; max-width:260px; }
  .mm-video-previa iframe, .mm-video-previa video { width:100%; height:100%; border:0; display:block; }
  .mm-ok { color:#1B8A76; font-weight:700; }
  .mm-suporte { font-size:.76rem; color:var(--ink-3); margin:10px 0 0; line-height:1.6; }
  .mm-suporte code { background:var(--soft); padding:1px 5px; border-radius:4px; font-size:.72rem; }

  /* Galeria: lista de imagens escolhidas */
  .mm-itens { display:flex; flex-direction:column; gap:8px; margin-top:12px; }
  .mm-item { display:grid; grid-template-columns:22px 84px minmax(0,1fr) auto; gap:10px; align-items:center; padding:8px 10px; border:1px solid var(--line); border-radius:10px; background:#FAFBFC; }
  .mm-item.arrastando { opacity:.5; }
  .mm-item .alca { color:#C7CBD1; cursor:grab; touch-action:none; user-select:none; display:flex; justify-content:center; }
  .mm-item img { width:84px; height:63px; object-fit:cover; border-radius:6px; background:var(--soft); }
  .mm-item input[type=text] { padding:7px 10px; font-size:.82rem; }
  .mm-item .rem { background:none; border:none; padding:4px; cursor:pointer; font:inherit; font-size:.74rem; font-weight:700; color:var(--red); }
  .mm-item .n { font-size:.7rem; color:var(--ink-3); font-weight:700; }
  .mm-cols { display:flex; gap:12px; align-items:center; margin-top:14px; flex-wrap:wrap; }
  .mm-cols label { margin:0; text-transform:none; letter-spacing:0; font-size:.8rem; }
  .mm-cols .hero-tabs { margin:0; }

  @media (max-width: 640px) {
    .mm { padding:10px 8px; }
    .mm-corpo, .mm-cabeca, .mm-pe { padding-left:14px; padding-right:14px; }
    .mm-item { grid-template-columns:18px 64px minmax(0,1fr); }
    .mm-item .rem { grid-column:3; justify-self:end; }
    .mm-item img { width:64px; height:48px; }
  }
</style>

<!-- Imagem -->
<div class="mm" id="mmImagem" role="dialog" aria-modal="true" aria-labelledby="mmImagemTitulo">
  <div class="card">
    <div class="mm-cabeca"><h2 id="mmImagemTitulo">Inserir imagem</h2><button type="button" data-fechar="mmImagem">fechar</button></div>
    <div class="mm-corpo">
      <div class="hero-tabs" role="tablist">
        <button type="button" class="on" data-aba="mmImagem" data-painel="mmImagemEnviar">Enviar do computador</button>
        <button type="button" data-aba="mmImagem" data-painel="mmImagemEnviadas">Já enviadas</button>
      </div>
      <div id="mmImagemEnviar">
        <div class="mm-zona" id="mmImagemZona"><strong>Arraste a imagem aqui</strong>ou clique para escolher no computador. JPG, PNG ou WEBP até 8 MB; fotos grandes são reduzidas para 2000 px.</div>
        <input type="file" id="mmImagemArquivo" accept="image/*" hidden />
        <div class="mm-progresso" id="mmImagemProgresso"><i></i></div>
      </div>
      <div id="mmImagemEnviadas" hidden>
        <p class="mm-intro">Clique numa imagem para escolher.</p>
        <div class="mm-grade" id="mmImagemGrade"></div>
        <p class="hint" id="mmImagemGradeMsg"></p>
      </div>
      <div class="mm-previa" id="mmImagemPrevia" hidden>
        <img id="mmImagemPreviaImg" alt="" />
        <div class="info">
          <label style="margin-top:0">Descrição da imagem (texto alternativo)</label>
          <input type="text" id="mmImagemAlt" placeholder="Ex.: Equipe da Mclair reunida no escritório" maxlength="200" />
          <p class="hint">Uma frase curta dizendo o que aparece na foto. Quem usa leitor de tela ouve isso, e o Google lê também. Já veio preenchida a partir do nome do arquivo; ajuste se quiser.</p>
          <p class="hint" id="mmImagemInfo"></p>
        </div>
      </div>
    </div>
    <div class="mm-pe"><p class="mm-erro" id="mmImagemErro"></p><button type="button" class="btn secondary" data-fechar="mmImagem">Cancelar</button><button type="button" class="btn" id="mmImagemInserir" disabled>Inserir imagem</button></div>
  </div>
</div>

<!-- Vídeo -->
<div class="mm" id="mmVideo" role="dialog" aria-modal="true" aria-labelledby="mmVideoTitulo">
  <div class="card">
    <div class="mm-cabeca"><h2 id="mmVideoTitulo">Inserir vídeo</h2><button type="button" data-fechar="mmVideo">fechar</button></div>
    <div class="mm-corpo">
      <label style="margin-top:10px">Endereço do vídeo</label>
      <input type="text" id="mmVideoUrl" placeholder="https://www.youtube.com/watch?v=..." autocomplete="off" />
      <p class="hint" id="mmVideoStatus">Cole o link completo. O player entra numa linha só, no lugar onde o cursor está.</p>
      <div class="mm-video-previa" id="mmVideoPrevia"></div>
      <p class="mm-suporte">Funcionam: YouTube (inclusive Shorts e youtu.be), Instagram (reel ou post), TikTok no formato <code>tiktok.com/@perfil/video/123...</code>, Vimeo e arquivos <code>.mp4</code> ou <code>.webm</code>. Não funcionam: links curtos <code>vm.tiktok.com</code>, Facebook, Google Drive e vídeos privados.</p>
    </div>
    <div class="mm-pe"><p class="mm-erro" id="mmVideoErro"></p><button type="button" class="btn secondary" data-fechar="mmVideo">Cancelar</button><button type="button" class="btn" id="mmVideoInserir" disabled>Inserir vídeo</button></div>
  </div>
</div>

<!-- Galeria -->
<div class="mm" id="mmGaleria" role="dialog" aria-modal="true" aria-labelledby="mmGaleriaTitulo">
  <div class="card">
    <div class="mm-cabeca"><h2 id="mmGaleriaTitulo">Galeria de imagens</h2><button type="button" data-fechar="mmGaleria">fechar</button></div>
    <div class="mm-corpo">
      <p class="mm-intro">Várias fotos numa grade, que abrem em tela cheia no site. Envie do computador ou escolha entre as já enviadas; depois arraste pela alça para mudar a ordem e escreva uma legenda se quiser.</p>
      <div class="hero-tabs" role="tablist">
        <button type="button" class="on" data-aba="mmGaleria" data-painel="mmGaleriaEnviar">Enviar do computador</button>
        <button type="button" data-aba="mmGaleria" data-painel="mmGaleriaEnviadas">Já enviadas</button>
      </div>
      <div id="mmGaleriaEnviar">
        <div class="mm-zona" id="mmGaleriaZona"><strong>Arraste várias imagens aqui</strong>ou clique para escolher no computador (pode selecionar mais de uma).</div>
        <input type="file" id="mmGaleriaArquivos" accept="image/*" multiple hidden />
        <div class="mm-progresso" id="mmGaleriaProgresso"><i></i></div>
        <p class="hint" id="mmGaleriaProgressoTxt"></p>
      </div>
      <div id="mmGaleriaEnviadas" hidden>
        <p class="mm-intro">Clique para incluir ou tirar da galeria.</p>
        <div class="mm-grade" id="mmGaleriaGrade"></div>
        <p class="hint" id="mmGaleriaGradeMsg"></p>
      </div>
      <div class="mm-itens" id="mmGaleriaItens"></div>
      <div class="mm-cols">
        <label>Colunas</label>
        <div class="hero-tabs" role="radiogroup" id="mmGaleriaCols">
          <button type="button" data-cols="auto" class="on">Automático</button>
          <button type="button" data-cols="2">2</button>
          <button type="button" data-cols="3">3</button>
          <button type="button" data-cols="4">4</button>
        </div>
        <span class="hint" style="margin:0">No celular a grade vira duas colunas.</span>
      </div>
    </div>
    <div class="mm-pe"><p class="mm-erro" id="mmGaleriaErro"></p><button type="button" class="btn secondary" data-fechar="mmGaleria">Cancelar</button><button type="button" class="btn" id="mmGaleriaInserir" disabled>Inserir galeria</button></div>
  </div>
</div>
<?php
}
