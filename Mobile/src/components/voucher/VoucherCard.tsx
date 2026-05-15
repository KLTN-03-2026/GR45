import React from 'react';
import { StyleSheet, Text, View, TouchableOpacity, Dimensions } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { IconSymbol } from '@/src/components/ui/icon-symbol';
import { format } from 'date-fns';
import { vi } from 'date-fns/locale';

const { width } = Dimensions.get('window');

type VoucherCardProps = {
  voucher: any;
  onPress?: () => void;
  isHunted?: boolean;
  isHunting?: boolean;
  type?: 'hunt' | 'my';
};

export const VoucherCard = ({ voucher, onPress, isHunted, isHunting, type = 'hunt' }: VoucherCardProps) => {
  const isPercent = voucher.loai_voucher === 'percent';
  const valueDisplay = isPercent 
    ? `${parseFloat(voucher.gia_tri)}%` 
    : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(voucher.gia_tri).replace('₫', 'đ');

  const expiryDate = voucher.ngay_ket_thuc 
    ? format(new Date(voucher.ngay_ket_thuc), 'dd/MM/yyyy') 
    : 'Không giới hạn';

  const usagePercent = voucher.so_luong 
    ? Math.min(100, Math.round(((voucher.so_luong - voucher.so_luong_con_lai) / voucher.so_luong) * 100))
    : 0;

  return (
    <View style={styles.container}>
      <View style={styles.card}>
        {/* Left Side: Value */}
        <LinearGradient
          colors={isHunted ? ['#94a3b8', '#64748b'] : ['#4f46e5', '#3730a3']}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={styles.leftPart}
        >
          <Text style={styles.typeLabel}>{isPercent ? 'GIẢM %' : 'GIẢM TIỀN'}</Text>
          <Text style={styles.valueText}>{valueDisplay}</Text>
          
          {/* Punch holes decoration */}
          <View style={[styles.punch, styles.punchTop]} />
          <View style={[styles.punch, styles.punchBottom]} />
        </LinearGradient>

        {/* Right Side: Info */}
        <View style={styles.rightPart}>
          <View style={styles.infoContainer}>
            <Text style={styles.title} numberOfLines={1}>{voucher.ten_voucher}</Text>
            <View style={styles.codeContainer}>
              <Text style={styles.codeLabel}>Mã: </Text>
              <Text style={styles.codeText}>{voucher.ma_voucher}</Text>
            </View>
            
            <Text style={styles.expiryText}>HSD: {expiryDate}</Text>

            {type === 'hunt' && (
              <View style={styles.progressSection}>
                <View style={styles.progressHeader}>
                  <Text style={styles.progressLabel}>Còn lại {voucher.so_luong_con_lai}</Text>
                  <Text style={styles.progressValue}>{100 - usagePercent}%</Text>
                </View>
                <View style={styles.progressBar}>
                  <View style={[styles.progressFill, { width: `${100 - usagePercent}%` }]} />
                </View>
              </View>
            )}
          </View>

          {onPress && (
            <TouchableOpacity 
              style={[
                styles.actionBtn, 
                isHunted && styles.disabledBtn,
                type === 'my' && styles.useBtn
              ]} 
              onPress={onPress}
              disabled={isHunted || isHunting}
            >
              <Text style={[styles.actionText, type === 'my' && styles.useText]}>
                {isHunting ? '...' : (type === 'my' ? 'Dùng ngay' : (isHunted ? 'Đã sở hữu' : 'Săn ngay'))}
              </Text>
            </TouchableOpacity>
          )}
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginBottom: 16,
    paddingHorizontal: 16,
  },
  card: {
    flexDirection: 'row',
    backgroundColor: '#ffffff',
    borderRadius: 16,
    height: 120,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 5,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#f1f5f9',
  },
  leftPart: {
    width: 100,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 10,
  },
  typeLabel: {
    color: 'rgba(255,255,255,0.8)',
    fontSize: 10,
    fontWeight: '700',
    marginBottom: 4,
  },
  valueText: {
    color: '#ffffff',
    fontSize: 18,
    fontWeight: '900',
    textAlign: 'center',
  },
  rightPart: {
    flex: 1,
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
  },
  infoContainer: {
    flex: 1,
    justifyContent: 'center',
  },
  title: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: 4,
  },
  codeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  codeLabel: {
    fontSize: 12,
    color: '#64748b',
  },
  codeText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#4f46e5',
    backgroundColor: '#eef2ff',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  expiryText: {
    fontSize: 11,
    color: '#94a3b8',
    marginBottom: 8,
  },
  progressSection: {
    marginTop: 'auto',
  },
  progressHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  progressLabel: {
    fontSize: 10,
    fontWeight: '600',
    color: '#64748b',
  },
  progressValue: {
    fontSize: 10,
    fontWeight: '600',
    color: '#4f46e5',
  },
  progressBar: {
    height: 4,
    backgroundColor: '#f1f5f9',
    borderRadius: 2,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: '#4f46e5',
  },
  actionBtn: {
    backgroundColor: '#f5f3ff',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 10,
    marginLeft: 8,
    minWidth: 80,
    alignItems: 'center',
  },
  actionText: {
    color: '#4f46e5',
    fontSize: 12,
    fontWeight: '700',
  },
  disabledBtn: {
    backgroundColor: '#f1f5f9',
  },
  useBtn: {
    backgroundColor: '#4f46e5',
  },
  useText: {
    color: '#ffffff',
  },
  punch: {
    position: 'absolute',
    right: -10,
    width: 20,
    height: 20,
    backgroundColor: '#ffffff',
    borderRadius: 10,
    zIndex: 10,
    borderWidth: 1,
    borderColor: '#f1f5f9',
  },
  punchTop: {
    top: -10,
  },
  punchBottom: {
    bottom: -10,
  },
});
