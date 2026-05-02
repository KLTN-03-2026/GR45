/**
 * Tiện ích nén ảnh tại Frontend trước khi upload
 */
export const compressImage = async (file, { maxWidth = 1024, maxHeight = 1024, quality = 0.7 } = {}) => {
  return new Promise((resolve, reject) => {
    if (!file || !file.type.startsWith('image/')) {
      return resolve(file); // Không phải ảnh thì trả về file gốc
    }

    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (event) => {
      const img = new Image();
      img.src = event.target.result;
      img.onload = () => {
        const canvas = document.createElement('canvas');
        let width = img.width;
        let height = img.height;

        // Tính toán tỷ lệ để resize
        if (width > height) {
          if (width > maxWidth) {
            height *= maxWidth / width;
            width = maxWidth;
          }
        } else {
          if (height > maxHeight) {
            width *= maxHeight / height;
            height = maxHeight;
          }
        }

        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);

        // Chuyển canvas thành Blob (nén ở định dạng webp hoặc jpeg)
        canvas.toBlob(
          (blob) => {
            if (!blob) {
              return resolve(file);
            }
            // Tạo file mới từ blob để giữ nguyên tên file
            const compressedFile = new File([blob], file.name, {
              type: 'image/webp',
              lastModified: Date.now(),
            });
            resolve(compressedFile);
          },
          'image/webp', // Ưu tiên nén sang webp cho nhẹ
          quality
        );
      };
      img.onerror = (err) => reject(err);
    };
    reader.onerror = (err) => reject(err);
  });
};
