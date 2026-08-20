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
     *   savePayload {object}  저장 요청에 함께 보낼 값 (돌려받을 목록의 모양 등)
     *   importPayload {object|func} 엑셀 업로드에 함께 보낼 값 (기본값·시트 이름 등)
     *   deleteWarning {string} 삭제 확인창에 덧붙일 경고 (딸린 자료가 함께 사라질 때)
     *   onRowDblClick {func}  읽기전용 상세 팝업 등 (row) => void
     *   rowCheckbox {bool}    행 체크박스 강제 표시 (읽기전용 목록에서 골라 처리할 때)
     *   buttons     {Array}   추가 툴바 버튼 [{ label, primary, onClick(grid) }]
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
            // 읽기전용 목록에서도 골라서 처리(공유·일괄 발송)해야 할 때가 있어 강제 옵션을 둔다.
            rowCheckbox: cfg.rowCheckbox === true || (editable && cfg.canDelete !== false),
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

        /* ---------- 콤보에서 고른 값의 타입을 되돌린다 ----------
         * <select>.value 는 언제나 문자열이다. 그래서 지자체를 고르면 3 이 '3' 이 되고,
         * wwGrid 는 라벨을 === 로 찾으므로 3 === '3' 이 아니라 라벨을 못 찾는다.
         * 그 결과 칸에 이름 대신 **id 숫자**가, 모집 여부에 '중지' 대신 '0' 이 뜬다.
         *
         * 같은 이유로 고르던 값을 그대로 다시 골라도 1 !== '1' 이라 '변경됨' 으로 잡혀,
         * 바꾼 것이 없는데 저장 대상이 된다.
         *
         * 고르기 전과 같은 타입으로 돌려놓아 두 가지를 함께 없앤다. wwGrid 는 여러
         * 프로젝트가 함께 쓰는 라이브러리라 손대지 않고 이쪽에서 감싼다.
         */
        var typedOption = {};
        (cfg.columns || []).forEach(function (col) {
            var opts = col.editor === 'combo' ? col.options
                : (col.editor === 'popup' && col.popup ? col.popup.items : null);
            if (!opts) return;
            var byText = {};
            opts.forEach(function (o) {
                var v = (o && typeof o === 'object') ? o.value : o;
                byText[String(v)] = v;
            });
            typedOption[col.name] = byText;
        });

        var commitValue = grid._commitValue.bind(grid);
        grid._commitValue = function (rowIndex, colName, value) {
            var byText = typedOption[colName];
            if (byText && typeof value === 'string' && Object.prototype.hasOwnProperty.call(byText, value)) {
                value = byText[value];
            }
            return commitValue(rowIndex, colName, value);
        };

        /* ---------- 저장 (수정추적 → 서버) ---------- */
        function save() {
            var mods = grid.getModifiedRows();
            var n = mods.updated.length + mods.added.length + mods.deleted.length;
            if (n === 0) { ndnToast('변경된 내용이 없습니다.', { type: 'info' }); return; }

            fetch(cfg.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                // savePayload 는 같은 저장 엔드포인트를 여러 화면이 쓸 때, 저장 뒤 돌려받을
                // 목록의 모양을 서버에 알려 주는 자리다 (예: 매칭 화면은 수요·배정 칸이 더 필요하다).
                body: JSON.stringify(Object.assign({}, cfg.savePayload || {}, mods)),
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
            // 기준정보처럼 다른 화면이 매달려 있는 표는, 무엇이 함께 사라지는지
            // 저장하기 전에 알려 준다 (cfg.deleteWarning).
            ndnConfirm(checked.length + '개 행을 삭제 목록에 넣습니다. (저장 시 반영)'
                + (cfg.deleteWarning ? ' ' + cfg.deleteWarning : ''), {
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
                // 화면이 붙여 보내고 싶은 값 (예: 명단에 칸이 없을 때 쓸 국적·지역 기본값).
                // 함수로 주면 업로드하는 순간의 선택을 읽는다.
                var extra = typeof cfg.importPayload === 'function' ? cfg.importPayload() : cfg.importPayload;
                Object.keys(extra || {}).forEach(function (k) {
                    if (extra[k] !== null && extra[k] !== undefined && extra[k] !== '') fd.append(k, extra[k]);
                });
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
        // 화면별 추가 동작 (예: 체크한 점검표를 관계기관에 공유)
        (cfg.buttons || []).forEach(function (b) {
            bar.appendChild(btn(b.label, b.primary ? 'ndn-gridbar__btn--primary' : '', function () { b.onClick(grid); }));
        });
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

        // 인스턴스를 host 요소에 노출 (외부 버튼 연동·디버깅·테스트용)
        host.wwgrid = grid;

        return grid;
    };
})();
