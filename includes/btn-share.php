
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
async function shareVoucher() {
  const voucher = document.getElementById('voucherCapture');
  if (!voucher) return;

  const codigoEl = voucher.querySelector('[data-codigo]');
  const codigo = codigoEl?.dataset.codigo || 'comprobante';

  if (typeof html2canvas === 'undefined') return;

  try {
    const canvas = await html2canvas(voucher, {
      scale: 2,
      backgroundColor: '#ffffff',
      useCORS: true
    });

    canvas.toBlob(async (blob) => {
      if (!blob) return;

      const fileName = `${codigo}.png`;
      const file = new File([blob], fileName, { type: 'image/png' });

      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({
          files: [file],
          title: 'Comprobante de venta',
          text: 'Aquí está tu comprobante'
        });
      } else {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      }
    });
  } catch (err) {
    return;
  }
}
</script>
