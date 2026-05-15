import React, { useEffect, useState } from 'react';
import { StyleSheet, Text, View, ScrollView, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useRouter } from 'expo-router';
import { VoucherCard } from '@/src/components/voucher/VoucherCard';
import clientApi from '@/src/services/client-api';

export const VoucherSection = () => {
  const [vouchers, setVouchers] = useState([]);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    const fetchTopVouchers = async () => {
      try {
        const res = await clientApi.getHuntableVouchers();
        if (res.data.success) {
          // Lấy 3 cái hot nhất
          setVouchers((res.data.data || []).slice(0, 3));
        }
      } catch (error) {
        console.error('Lỗi tải voucher hot:', error);
      } finally {
        setLoading(false);
      }
    };
    fetchTopVouchers();
  }, []);

  if (!loading && vouchers.length === 0) return null;

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <View>
          <Text style={styles.title}>Ưu Đãi Độc Quyền 🎁</Text>
          <Text style={styles.subtitle}>Săn ngay voucher giảm giá cực hời.</Text>
        </View>
        <TouchableOpacity onPress={() => router.push('/vouchers/hunt')}>
          <Text style={styles.linkText}>Tất cả</Text>
        </TouchableOpacity>
      </View>

      {loading ? (
        <ActivityIndicator size="small" color="#4f46e5" style={{ marginVertical: 20 }} />
      ) : (
        <View>
          {vouchers.map((v: any) => (
            <VoucherCard 
              key={v.id} 
              voucher={v} 
              onPress={() => router.push('/vouchers/hunt')}
              type="hunt"
            />
          ))}
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginTop: 32,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    paddingHorizontal: 20,
    marginBottom: 16,
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 13,
    color: '#64748b',
  },
  linkText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#4f46e5',
  },
});
