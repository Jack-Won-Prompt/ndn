/* ==========================================================================
   NDN 운영 콘솔 — wwGrid 통합 헬퍼
   모든 리스트 화면이 wwGrid 로 동작하도록 표준 테마·툴바·저장/엑셀 흐름 제공.
   ========================================================================== */
(function () {
    'use strict';

    // 콘솔 무채색+teal 테마 (wwGrid CSS 변수 오버라이드)
    var THEME = {
        accent: '#1E9C92', accentDark: '#14807A',
        headerBg: '#F7F9FB', headerGroupBg: '#EAEFF3', headerHoverBg: '#EEF2F5',
        footerBg: '#F7F9FB', selectedBg: '#DFF2F0', selectedMixBg: '#C6E7E3',
        modifiedBg: '#FFF8E6', newBg: '#F0FBF4', summaryBg: '#F1F4F7',
        border: '#D5DBE3', rowBorder: '#EAEDF1', borderSub: '#B7BFCB',
    };

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.content : '';
    }

    // 그리드 본문 높이 = 뷰포트 - 그리드 시작위치 - (헤더/푸터/여백)
    function fitHeight(host) {
        var top = host.getBoundingClientRect().top + window.scrollY;
        return Math.max(220, Math.floor(window.innerHeight - top - 150));
    }

    function btn(label, cls, onClick) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'ndn-gridbar__btn' + (cls ? ' ' + cls : '');
        b.textContent = label;
        b.addEventListener('click', onClick);
        return b;
    }

    /**
     * @param {object} cfg
     *   el          {string}  마운트 DOM id
     *   columns     {Array}   wwGrid 컬럼 정의
     *   columnGroups{Array}   (선택) 헤더 그루핑
     *   data        {Array}   초기 데이터
     *   editable    {bool}    편집(등록/수정/삭제) 가능 여부 (기본 false)
     *   summary     {bool}    합계행
     *   title       {string}  엑셀 파일명·제목
     *   saveUrl     {string}  변경 저장 POST 엔드포인트 (editable 시)
     *   importUrl   {string}  엑셀 업로드 POST 엔드포인트 (editable 시)
     *   newRow      {object}  '신규 행' 기본값
     *   onRowDblClick {func}  읽기전용 상세 팝업 등 (row) => void
     */
    window.wwConsole = function (cfg) {
        var host = document.getElementById(cfg.el);
        var editable = cfg.editable === true;

        // 툴바 자리(그리드보다 위) 먼저 삽입 → 높이 계산이 정확해진다
        var bar = document.createElement('div');
        bar.className = 'ndn-gridbar';
        host.parentNode.insertBefore(bar, host);

        var grid = new wwGrid({
            el: host,
            data: cfg.data || [],
            columns: cfg.columns,
            columnGroups: cfg.columnGroups || [],
            rowKey: 'id',
            height: cfg.height || fitHeight(host),
            editable: editable,
            rowCheckbox: editable && cfg.canDelete !== false,
            rowNumber: true,
            summary: cfg.summary === true,
            toolbar: false,
            footer: true,
            theme: THEME,
        });

        // 행이 적어도 그리드가 뷰포트 높이를 채우도록 고정 높이 적용
        // (짧게 끝나 아래에 페이지 공백이 생기는 것 방지)
        if (!cfg.height && grid._wrapEl) {
            grid._wrapEl.style.height = fitHeight(host) + 'px';
        }

        /* ---------- 저장 (수정추적 → 서버) ---------- */
        function save() {
            var mods = grid.getModifiedRows();
            var n = mods.updated.length + mods.added.length + mods.deleted.length;
            if (n === 0) { ndnToast('변경된 내용이 없습니다.', { type: 'info' }); return; }

            fetch(cfg.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: JSON.stringify(mods),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok) {
                        if (Array.isArray(res.j.rows)) grid.setData(res.j.rows);
                        ndnToast(res.j.message || '저장했습니다.', { type: 'success' });
                    } else {
                        ndnToast(res.j.message || '저장하지 못했습니다.', { type: 'error', title: '저장 실패' });
                    }
                })
                .catch(function () { ndnToast('네트워크 오류로 저장하지 못했습니다.', { type: 'error', title: '저장 실패' }); });
        }

        /* ---------- 삭제 (체크 행) ---------- */
        function removeChecked() {
            var checked = grid.getCheckedRows();
            if (!checked.length) { ndnToast('삭제할 행을 선택하세요.', { type: 'info' }); return; }
            ndnConfirm(checked.length + '개 행을 삭제 목록에 넣습니다. (저장 시 반영)', {
                title: '행 삭제', okText: '삭제', danger: true,
            }).then(function (ok) { if (ok) grid.removeCheckedRows(); });
        }

        /* ---------- 엑셀 업로드(가져오기) ---------- */
        function excelUpload() {
            var input = document.createElement('input');
            input.type = 'file';
            input.accept = '.xlsx,.xls,.csv';
            input.addEventListener('change', function () {
                if (!input.files.length) return;
                var fd = new FormData();
                fd.append('file', input.files[0]);
                fetch(cfg.importUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    body: fd,
                })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (res.ok && Array.isArray(res.j.rows)) {
                            if (res.j.replace) {
                                // 서버가 이미 저장함 → 전체 데이터 교체
                                grid.setData(res.j.rows);
                                ndnToast(res.j.message || '가져와 저장했습니다.', { type: 'success' });
                            } else {
                                // 검토용 신규 행으로 추가 (사용자가 확인 후 저장)
                                res.j.rows.forEach(function (row) { grid.addRow(row); });
                                ndnToast(res.j.rows.length + '행을 불러왔습니다. 검토 후 저장하세요.', { type: 'success' });
                            }
                        } else {
                            ndnToast(res.j.message || '엑셀을 읽지 못했습니다.', { type: 'error', title: '가져오기 실패' });
                        }
                    })
                    .catch(function () { ndnToast('엑셀 업로드에 실패했습니다.', { type: 'error', title: '가져오기 실패' }); });
            });
            input.click();
        }

        /* ---------- 툴바 구성 ---------- */
        bar.appendChild(btn('엑셀 다운로드', '', function () { grid.downloadExcel({ filename: cfg.title || 'export' }); }));
        if (editable) {
            if (cfg.importUrl) bar.insertBefore(btn('엑셀 업로드', '', excelUpload), bar.firstChild);
            var sep = document.createElement('span'); sep.style.cssText = 'width:1px;height:20px;background:var(--mv2-border-default);margin:0 4px;'; bar.appendChild(sep);
            if (cfg.canAdd !== false) bar.appendChild(btn('신규 행', '', function () { grid.addRow(cfg.newRow || {}); }));
            if (cfg.canDelete !== false) bar.appendChild(btn('행 삭제', '', removeChecked));
            bar.appendChild(btn('변경 취소', '', function () { grid.resetModified(); }));
            bar.appendChild(btn('변경 저장', 'ndn-gridbar__btn--primary', save));
        }

        /* ---------- 읽기전용 상세 팝업(더블클릭) ---------- */
        if (typeof cfg.onRowDblClick === 'function') {
            host.addEventListener('dblclick', function (e) {
                // 편집 가능한 셀 더블클릭은 에디터에 양보
                var cell = e.target.closest('[data-col-name]');
                if (cell) {
                    var colName = cell.getAttribute('data-col-name');
                    var col = (cfg.columns || []).find(function (c) { return c.name === colName; });
                    if (col && col.editor) return;
                }
                var tr = e.target.closest('[data-row-index]');
                if (!tr) return;
                var idx = parseInt(tr.getAttribute('data-row-index'), 10);
                if (isNaN(idx)) return;
                var row = grid.getData()[idx];
                if (row) cfg.onRowDblClick(row);
            });
        }

        if (!cfg.height) {
            window.addEventListener('resize', function () {
                if (grid._wrapEl) {
                    var hpx = fitHeight(host) + 'px';
                    grid._wrapEl.style.maxHeight = hpx;
                    grid._wrapEl.style.height = hpx;
                }
            });
        }

        return grid;
    };
})();
