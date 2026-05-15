import React from 'react';
import { StyleSheet, Text, View, TouchableOpacity, Dimensions } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import Animated, { FadeInRight, Layout } from 'react-native-reanimated';
import { format } from 'date-fns';
import { vi } from 'date-fns/locale';

const { width } = Dimensions.get('window');

type TicketCardProps = {
  ticket: any;
  onPress: () => void;
  onRatingPress?: () => void;
  index: number;
};

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
};

const extractTime = (timeStr: string) => {
  if (!timeStr) return "00:00";
  // Nếu là ISO hoặc Datetime: "2026-05-12T15:00:00" hoặc "2026-05-12 15:00:00"
  if (timeStr.includes('T')) return timeStr.split('T')[1].substring(0, 5);
  if (timeStr.includes(' ')) return timeStr.split(' ')[1].substring(0, 5);
  return timeStr.substring(0, 5);
};

const isPastTrip = (ticket: any) => {
  const cx = ticket?.chuyen_xe || ticket?.chuyenXe;
  if (!cx?.ngay_khoi_hanh) return false;
  
  const dateStr = cx.ngay_khoi_hanh.includes('T') ? cx.ngay_khoi_hanh.split('T')[0] : cx.ngay_khoi_hanh;
  const timeStr = extractTime(cx.gio_khoi_hanh);
  
  const dep = new Date(`${dateStr}T${timeStr}:00`);
  const duration = cx.tuyen_duong?.gio_du_kien || 2;
  const arrival = new Date(dep.getTime() + duration * 60 * 60 * 1000);

  return arrival.getTime() < Date.now();
};

const getStatusStyles = (status: string, isPast: boolean) => {
  // 1. Trạng thái đã hoàn thành tường minh từ backend
  if (status === 'da_hoan_thanh' || status === 'hoan_thanh') {
    return { bg: '#eff6ff', text: '#3b82f6', label: 'Hoàn thành' };
  }

  // 2. Vé đã thanh toán nhưng chuyến xe đã qua → coi là hoàn thành
  if (isPast && status === 'da_thanh_toan') {
    return { bg: '#eff6ff', text: '#3b82f6', label: 'Hoàn thành' };
  }

  // 3. Các trạng thái khác
  switch (status) {
    case 'da_thanh_toan':
      return { bg: '#ecfdf5', text: '#059669', label: 'Đã thanh toán' };
    case 'dang_cho':
      return { bg: '#fffbeb', text: '#d97706', label: 'Chờ thanh toán' };
    case 'huy':
    case 'da_huy':
      return { bg: '#fef2f2', text: '#dc2626', label: 'Đã hủy' };
    default:
      return { bg: '#f3f4f6', text: '#6b7280', label: status || 'Không rõ' };
  }
};

export const TicketCard = ({ ticket, onPress, onRatingPress, index }: TicketCardProps) => {
  const chuyenXe = ticket.chuyen_xe || ticket.chuyenXe || {};
  const tuyenDuong = chuyenXe.tuyen_duong || chuyenXe.tuyenDuong || {};
  const nhaXe = tuyenDuong.nha_xe || tuyenDuong.nhaXe || {};

  const isPast = isPastTrip(ticket);
  const statusInfo = getStatusStyles(ticket.tinh_trang, isPast);
  const isCompleted = ticket.tinh_trang === 'hoan_thanh' || ticket.tinh_trang === 'da_hoan_thanh' || (isPast && ticket.tinh_trang === 'da_thanh_toan');
  const hasRating = ticket.danh_gia != null || ticket.has_rating;
  
  let displayDate = '...';
  try {
    if (chuyenXe.ngay_khoi_hanh) {
       displayDate = format(new Date(chuyenXe.ngay_khoi_hanh), 'dd MMMM, yyyy', { locale: vi });
    }
  } catch(e) {}

  const depTime = extractTime(chuyenXe.gio_khoi_hanh);
  const duration = tuyenDuong.gio_du_kien || 2;
  
  // Tính giờ đến
  const getArrivalTime = (start: string, dur: number) => {
    try {
      const [h, m] = start.split(':').map(Number);
      const date = new Date(2000, 0, 1, h, m);
      date.setHours(date.getHours() + dur);
      return format(date, 'HH:mm');
    } catch(e) {
      return '--:--';
    }
  };
  const arrTime = getArrivalTime(depTime, duration);

  return (
    <Animated.View 
      entering={FadeInRight.delay(index * 100).springify()}
      layout={Layout.springify()}
    >
      <TouchableOpacity 
        style={styles.card} 
        onPress={onPress}
        activeOpacity={0.8}
      >
        <View style={styles.headerRow}>
          <View style={styles.busIconContainer}>
             <Ionicons name="bus-outline" size={20} color="#0052cc" />
          </View>
          <Text style={styles.busName} numberOfLines={1}>{nhaXe.ten_nha_xe || 'Nhà xe'}</Text>
          <View style={[styles.statusBadge, { backgroundColor: statusInfo.bg }]}>
            <Text style={[styles.statusText, { color: statusInfo.text }]}>{statusInfo.label}</Text>
          </View>
        </View>

        <View style={styles.routeRow}>
          <View style={styles.stopPoint}>
             <Text style={styles.timeText}>{depTime}</Text>
             <Text style={styles.locationText} numberOfLines={1}>{tuyenDuong.diem_bat_dau || 'Nơi đi'}</Text>
          </View>

          <View style={styles.pathContainer}>
             <View style={styles.dot} />
             <View style={styles.dashLine} />
             <Ionicons name="chevron-forward" size={16} color="#94a3b8" />
             <View style={styles.dashLine} />
             <View style={[styles.dot, styles.dotFill]} />
          </View>

          <View style={[styles.stopPoint, { alignItems: 'flex-end' }]}>
             <Text style={styles.timeText}>{arrTime}</Text>
             <Text style={styles.locationText} numberOfLines={1}>{tuyenDuong.diem_ket_thuc || 'Nơi đến'}</Text>
          </View>
        </View>

        <View style={styles.divider} />

        <View style={styles.footerRow}>
           <View style={styles.footerCol}>
              <Text style={styles.label}>Ngày đi</Text>
              <Text style={styles.value}>{displayDate}</Text>
           </View>
           <View style={styles.footerCol}>
              <Text style={[styles.label, { textAlign: 'right' }]}>Tổng tiền</Text>
              <Text style={styles.priceText}>{formatCurrency(ticket.tong_tien || 0)}</Text>
           </View>
        </View>

        {isCompleted && !hasRating && (
          <View style={styles.ratingRow}>
             <TouchableOpacity style={styles.ratingBtn} onPress={(e) => {
               e.stopPropagation();
               onRatingPress?.();
             }}>
               <Ionicons name="star-outline" size={16} color="#0052cc" />
               <Text style={styles.ratingBtnText}>Đánh giá ngay</Text>
             </TouchableOpacity>
          </View>
        )}

        {/* Ticket holes aesthetic */}
        <View style={[styles.hole, { left: -10 }]} />
        <View style={[styles.hole, { right: -10 }]} />
      </TouchableOpacity>
    </Animated.View>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
    marginHorizontal: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 12,
    elevation: 3,
    position: 'relative',
    overflow: 'hidden',
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  busIconContainer: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#eff6ff',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
  },
  busName: {
    flex: 1,
    fontSize: 16,
    fontWeight: '600',
    color: '#1e293b',
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 20,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  routeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 20,
  },
  stopPoint: {
    flex: 2,
  },
  timeText: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#0f172a',
    marginBottom: 4,
  },
  locationText: {
    fontSize: 14,
    color: '#64748b',
  },
  pathContainer: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: '#cbd5e1',
  },
  dotFill: {
    backgroundColor: '#0052cc',
    borderColor: '#0052cc',
  },
  dashLine: {
    flex: 1,
    height: 1,
    backgroundColor: '#e2e8f0',
    marginHorizontal: 4,
  },
  divider: {
    height: 1,
    borderStyle: 'dashed',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 1,
    marginVertical: 12,
  },
  hole: {
    position: 'absolute',
    width: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: '#f8fafc', // matches outer container bg
    top: '67%', // align with divider
  },
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  footerCol: {
    flex: 1,
  },
  label: {
    fontSize: 12,
    color: '#94a3b8',
    marginBottom: 4,
  },
  value: {
    fontSize: 14,
    fontWeight: '500',
    color: '#334155',
  },
  priceText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#0052cc',
    textAlign: 'right',
  },
  ratingRow: {
    marginTop: 12,
    flexDirection: 'row',
    justifyContent: 'flex-end',
  },
  ratingBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#eff6ff',
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  ratingBtnText: {
    marginLeft: 6,
    fontSize: 13,
    fontWeight: '600',
    color: '#0052cc',
  }
});
