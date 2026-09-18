<dialog id="doc-viewer" class="w-[min(960px,95vw)] max-w-none rounded-2xl p-0 shadow-2xl backdrop:bg-black/70" aria-labelledby="doc-viewer-title">
  <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
    <h2 id="doc-viewer-title" class="min-w-0 flex-1 truncate font-semibold text-slate-900">Dokumen</h2>
    <a id="doc-viewer-download" href="#" class="btn-chip">Unduh</a>
    <button type="button" id="doc-viewer-close" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Tutup">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <div id="doc-viewer-body" class="flex max-h-[80vh] min-h-[50vh] items-center justify-center overflow-auto bg-slate-100 p-3"></div>
  <p class="border-t border-slate-200 px-4 py-2 text-xs text-slate-500">Dokumen pribadi peserta. Jangan membagikan ke pihak yang tidak berwenang. Akses ini tercatat di log.</p>
</dialog>
