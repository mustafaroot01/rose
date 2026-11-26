const p=(t,e)=>{const n=URL.createObjectURL(t),o=document.createElement("a");o.href=n,o.download=e,document.body.appendChild(o),o.click(),document.body.removeChild(o),URL.revokeObjectURL(n)},l=t=>{const e=t==null?"":String(t);return/[",\n]/.test(e)?`"${e.replace(/"/g,'""')}"`:e},h=(t,e,n)=>{const o=e.map(a=>l(a.title)),r=n.map(a=>e.map(i=>l(i.value(a)))),s=[o,...r].map(a=>a.join(",")).join(`\r
`),d=new Blob([`\uFEFF${s}`],{type:"text/csv;charset=utf-8;"});p(d,t.endsWith(".csv")?t:`${t}.csv`)},c=t=>String(t??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"),b=(t,e,n)=>{const o=window.open("","_blank");if(!o)return;const r=e.map(d=>`<th>${c(d.title)}</th>`).join(""),s=n.map(d=>`<tr>${e.map(a=>`<td>${c(a.value(d))}</td>`).join("")}</tr>`).join("");o.document.write(`<!doctype html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="utf-8">
  <title>${c(t)}</title>
  <style>
    * { font-family: "Thmanyah Sans", system-ui, sans-serif; }
    body { padding: 28px; color: #1a1a1a; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .meta { color: #6b7280; font-size: 12px; margin-bottom: 18px; }
    table { inline-size: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: start; }
    thead th { background: #059669; color: #fff; }
    tbody tr:nth-child(even) { background: #f3faf6; }
  </style>
</head>
<body>
  <h1>${c(t)}</h1>
  <div class="meta">${new Date().toLocaleString("en-GB")} — ${n.length} صف</div>
  <table>
    <thead><tr>${r}</tr></thead>
    <tbody>${s}</tbody>
  </table>
  <script>window.onload = () => { window.print() }<\/script>
</body>
</html>`),o.document.close()};export{b as a,h as e};
