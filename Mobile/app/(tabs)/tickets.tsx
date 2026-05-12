import React, { useContext, useEffect, useState } from "react";
import { StyleSheet, Text, View, TouchableOpacity, FlatList, ActivityIndicator, RefreshControl, StatusBar } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { AuthContext } from "@/src/store/AuthContext";
import { useRouter } from "expo-router";
import clientApi from "@/src/services/client-api";
import { TicketCard } from "@/src/components/ticket/TicketCard";
import { Ionicons } from "@expo/vector-icons";
import Animated, { FadeIn, FadeOut, LinearTransition } from "react-native-reanimated";

type FilterType = 'all' | 'da_thanh_toan' | 'dang_cho' | 'history';

export default function TicketsScreen() {
  const { user } = useContext(AuthContext);
  const router = useRouter();
  const [tickets, setTickets] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState<FilterType>('all');

  const fetchTickets = async (isRefresh = false) => {
    if (!user) return;
    
    if (isRefresh) setRefreshing(true);
    else setLoading(true);

    try {
      // Map filter values to Backend query expectations if needed
      const params: any = {};
      if (filter === 'history') {
         params.tinh_trang = 'hoan_thanh'; // will be intercepted by logic in Service we read earlier
      } else if (filter !== 'all') {
         params.tinh_trang = filter;
      }

      const response = await clientApi.getTickets(params);
      if (response.data.success) {
        // backend might return direct data array or standard paginated object with 'data' field inside it
        const resultData = response.data.data;
        const dataArray = Array.isArray(resultData) ? resultData : (resultData.data || []);
        setTickets(dataArray);
      }
    } catch (error) {
      console.error("Lỗi tải vé:", error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    if (user) {
       fetchTickets();
    }
  }, [user, filter]);

  if (!user) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.unauthContainer}>
          <Ionicons name="ticket-outline" size={80} color="#cbd5e1" style={{ marginBottom: 24 }} />
          <Text style={styles.unauthTitle}>Quản lý vé của bạn</Text>
          <Text style={styles.message}>Đăng nhập để xem và quản lý những chuyến đi của bạn dễ dàng hơn</Text>
          <TouchableOpacity 
            style={styles.loginButton} 
            onPress={() => router.push("/login")}
            activeOpacity={0.8}
          >
            <Text style={styles.loginText}>Đăng nhập ngay</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const renderHeader = () => (
    <View style={styles.header}>
      <Text style={styles.pageTitle}>Vé Của Tôi</Text>
      <View style={styles.filterScroll}>
        {[
          { id: 'all', label: 'Tất cả' },
          { id: 'da_thanh_toan', label: 'Đã mua' },
          { id: 'dang_cho', label: 'Chờ t.toán' },
          { id: 'history', label: 'Lịch sử' }
        ].map((item) => (
          <TouchableOpacity
            key={item.id}
            style={[styles.filterChip, filter === item.id && styles.filterChipActive]}
            onPress={() => setFilter(item.id as FilterType)}
            activeOpacity={0.7}
          >
            <Text style={[styles.filterLabel, filter === item.id && styles.filterLabelActive]}>
              {item.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
    </View>
  );

  const renderEmpty = () => (
    <Animated.View 
      entering={FadeIn} 
      exiting={FadeOut}
      style={styles.emptyState}
    >
      <View style={styles.emptyIconContainer}>
         <Ionicons name="receipt-outline" size={48} color="#94a3b8" />
      </View>
      <Text style={styles.emptyTitle}>Không tìm thấy vé nào</Text>
      <Text style={styles.emptyDesc}>Có vẻ như bạn chưa có chuyến đi nào phù hợp.</Text>
      <TouchableOpacity 
         style={styles.exploreBtn}
         onPress={() => router.replace("/")}
      >
        <Text style={styles.exploreBtnText}>Tìm chuyến xe ngay</Text>
      </TouchableOpacity>
    </Animated.View>
  );

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor="#f8fafc" />
      <View style={styles.container}>
        {renderHeader()}

        {loading && !refreshing ? (
          <View style={styles.loaderContainer}>
            <ActivityIndicator size="large" color="#0052cc" />
            <Text style={styles.loadingText}>Đang tải danh sách...</Text>
          </View>
        ) : (
          <FlatList
            data={tickets}
            keyExtractor={(item) => item.id.toString()}
            renderItem={({ item, index }) => (
              <TicketCard 
                ticket={item} 
                index={index}
                onPress={() => router.push(`/ticket/${item.id}`)}
              />
            )}
            contentContainerStyle={styles.listContent}
            ListEmptyComponent={renderEmpty}
            showsVerticalScrollIndicator={false}
            itemLayoutAnimation={LinearTransition}
            refreshControl={
              <RefreshControl 
                refreshing={refreshing} 
                onRefresh={() => fetchTickets(true)} 
                colors={['#0052cc']}
                tintColor={'#0052cc'}
              />
            }
          />
        )}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  container: {
    flex: 1,
  },
  unauthContainer: { 
    flex: 1, 
    justifyContent: "center", 
    alignItems: "center", 
    padding: 32 
  },
  unauthTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#0f172a',
    marginBottom: 8,
  },
  message: { 
    fontSize: 16, 
    color: "#64748b", 
    marginBottom: 32, 
    textAlign: "center", 
    lineHeight: 24 
  },
  loginButton: { 
    backgroundColor: "#0052cc", 
    paddingVertical: 16, 
    paddingHorizontal: 48, 
    borderRadius: 30,
    shadowColor: "#0052cc", 
    shadowOffset: { width: 0, height: 4 }, 
    shadowOpacity: 0.25, 
    shadowRadius: 8,
    elevation: 4,
  },
  loginText: { color: "#ffffff", fontSize: 16, fontWeight: "600" },
  header: {
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 8,
    backgroundColor: '#f8fafc',
  },
  pageTitle: {
    fontSize: 28,
    fontWeight: '800',
    color: '#0f172a',
    marginBottom: 16,
  },
  filterScroll: {
    flexDirection: 'row',
    marginBottom: 8,
    gap: 8,
  },
  filterChip: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 20,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  filterChipActive: {
    backgroundColor: '#0052cc',
    borderColor: '#0052cc',
  },
  filterLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: '#64748b',
  },
  filterLabelActive: {
    color: '#ffffff',
    fontWeight: '600',
  },
  listContent: {
    padding: 16,
    paddingBottom: 40,
    flexGrow: 1,
  },
  loaderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 15,
    color: '#64748b',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingTop: 60,
  },
  emptyIconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#f1f5f9',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 20,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#334155',
    marginBottom: 8,
  },
  emptyDesc: {
    fontSize: 15,
    color: '#64748b',
    textAlign: 'center',
    paddingHorizontal: 40,
    marginBottom: 24,
    lineHeight: 22,
  },
  exploreBtn: {
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
    backgroundColor: '#eff6ff',
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  exploreBtnText: {
    color: '#1d4ed8',
    fontWeight: '600',
    fontSize: 15,
  }
});
