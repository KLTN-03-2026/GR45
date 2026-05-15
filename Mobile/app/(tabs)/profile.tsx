import { StyleSheet, Text, View, TouchableOpacity, ScrollView, RefreshControl } from "react-native";
import { useContext, useState, useEffect } from "react";
import { AuthContext } from "@/src/store/AuthContext";
import { useRouter } from "expo-router";
import { LinearGradient } from 'expo-linear-gradient';
import { IconSymbol } from "@/src/components/ui/icon-symbol";
import Animated, { FadeInUp, FadeInRight } from 'react-native-reanimated';
import clientApi from "@/src/services/client-api";

export default function ProfileScreen() {
  const { user, signOut, refreshUser } = useContext(AuthContext);
  const router = useRouter();
  const [refreshing, setRefreshing] = useState(false);
  const [loyaltyInfo, setLoyaltyInfo] = useState<any>(null);

  const onRefresh = async () => {
    setRefreshing(true);
    await Promise.all([refreshUser(), fetchLoyalty()]);
    setRefreshing(false);
  };

  const fetchLoyalty = async () => {
    try {
      const res = await clientApi.getLoyaltyInfo();
      if (res.data.success) {
        setLoyaltyInfo(res.data.data);
      }
    } catch (error) {
      console.error("Lỗi lấy thông tin thành viên:", error);
    }
  };

  useEffect(() => {
    if (user) {
      fetchLoyalty();
    }
  }, [user]);

  if (!user) {
    return (
      <View style={styles.guestContainer}>
        <Animated.View entering={FadeInUp.duration(600)} style={styles.guestContent}>
          <View style={styles.guestIconContainer}>
            <IconSymbol name="person.circle.fill" size={100} color="#cbd5e1" />
          </View>
          <Text style={styles.guestTitle}>Xin chào khách hàng!</Text>
          <Text style={styles.guestMessage}>Vui lòng đăng nhập để xem thông tin tài khoản và tận hưởng các ưu đãi dành riêng cho thành viên.</Text>
          <TouchableOpacity 
            style={styles.loginButton} 
            onPress={() => router.push("/login")}
          >
            <Text style={styles.loginText}>Đăng nhập ngay</Text>
          </TouchableOpacity>
        </Animated.View>
      </View>
    );
  }

  const getRankConfig = (rank: string) => {
    const map: any = {
      dong: { label: "Hạng Đồng", colors: ["#b45309", "#d97706"], icon: "star.fill" },
      bac: { label: "Hạng Bạc", colors: ["#475569", "#64748b"], icon: "star.fill" },
      vang: { label: "Hạng Vàng", colors: ["#a16207", "#eab308"], icon: "star.fill" },
      bach_kim: { label: "Hạng Bạch Kim", colors: ["#1e40af", "#3b82f6"], icon: "star.fill" },
    };
    return map[rank] || { label: "Thành viên", colors: ["#1e293b", "#334155"], icon: "person.fill" };
  };

  const rankConfig = getRankConfig(loyaltyInfo?.hang_thanh_vien || user.diem_thanh_vien?.hang_thanh_vien || user.hang_thanh_vien);

  return (
    <ScrollView 
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
    >
      {/* Header Profile */}
      <View style={styles.header}>
        <Animated.View entering={FadeInUp.delay(200).duration(500)} style={styles.avatarContainer}>
          <Text style={styles.avatarText}>{(user.ten_khach_hang || user.ho_va_ten || "K").charAt(0).toUpperCase()}</Text>
        </Animated.View>
        <Animated.View entering={FadeInRight.delay(300).duration(500)} style={styles.userInfo}>
          <Text style={styles.userName}>{user.ten_khach_hang || user.ho_va_ten || "Khách Hàng"}</Text>
          <Text style={styles.userEmail}>{user.email || "Chưa cập nhật email"}</Text>
        </Animated.View>
      </View>

      {/* Membership Card */}
      <Animated.View entering={FadeInUp.delay(400).duration(600)} style={styles.cardWrapper}>
        <LinearGradient
          colors={rankConfig.colors as any}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={styles.loyaltyCard}
        >
          <View style={styles.cardHeader}>
            <View style={styles.rankBadge}>
              <IconSymbol name={rankConfig.icon} size={16} color="#ffffff" />
              <Text style={styles.rankText}>{rankConfig.label}</Text>
            </View>
            <Text style={styles.appName}>BusSafe Member</Text>
          </View>
          <View style={styles.pointsContainer}>
            <Text style={styles.pointsLabel}>Điểm hiện có</Text>
            <Text style={styles.pointsValue}>
              {loyaltyInfo?.diem_hien_tai ?? 
               (typeof user.diem_thanh_vien === 'object' ? user.diem_thanh_vien?.diem_hien_tai : user.diem_thanh_vien) ?? 
               0} 
              <Text style={styles.pointsUnit}> điểm</Text>
            </Text>
          </View>
        </LinearGradient>
      </Animated.View>

      {/* Quick Actions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Hoạt động của tôi</Text>
        <View style={styles.menuGrid}>
          <MenuCard 
            icon="ticket.fill" 
            label="Vé của tôi" 
            delay={500} 
            onPress={() => router.push("/(tabs)/tickets")} 
          />
          <MenuCard 
            icon="star.fill" 
            label="Đánh giá" 
            delay={600} 
            onPress={() => {}} // TODO: Navigate to Reviews
          />
          <MenuCard 
            icon="gift.fill" 
            label="Ưu đãi" 
            delay={700} 
            onPress={() => router.push("/vouchers/my-vouchers")} 
          />
        </View>
      </View>

      {/* Settings List */}
      <View style={[styles.section, { marginBottom: 30 }]}>
        <Text style={styles.sectionTitle}>Cài đặt tài khoản</Text>
        <View style={styles.settingsList}>
          <SettingItem 
            icon="person.fill" 
            label="Thông tin cá nhân" 
            onPress={() => router.push("/profile/edit-profile")} 
          />
          <SettingItem 
            icon="lock.fill" 
            label="Đổi mật khẩu" 
            onPress={() => router.push("/profile/change-password")} 
          />
          <SettingItem 
            icon="bell.fill" 
            label="Thông báo" 
            onPress={() => {}} 
          />
          <TouchableOpacity style={styles.logoutButton} onPress={signOut}>
            <IconSymbol name="rectangle.portrait.and.arrow.right" size={20} color="#ef4444" />
            <Text style={styles.logoutText}>Đăng xuất</Text>
          </TouchableOpacity>
        </View>
      </View>
    </ScrollView>
  );
}

function MenuCard({ icon, label, delay, onPress }: { icon: any, label: string, delay: number, onPress: () => void }) {
  return (
    <Animated.View entering={FadeInUp.delay(delay).duration(500)} style={styles.menuCardWrapper}>
      <TouchableOpacity style={styles.menuCard} onPress={onPress}>
        <View style={styles.menuIconContainer}>
          <IconSymbol name={icon} size={24} color="#0052cc" />
        </View>
        <Text style={styles.menuLabel}>{label}</Text>
      </TouchableOpacity>
    </Animated.View>
  );
}

function SettingItem({ icon, label, onPress }: { icon: any, label: string, onPress: () => void }) {
  return (
    <TouchableOpacity style={styles.settingItem} onPress={onPress}>
      <View style={styles.settingLeft}>
        <View style={styles.settingIconContainer}>
          <IconSymbol name={icon} size={20} color="#64748b" />
        </View>
        <Text style={styles.settingLabel}>{label}</Text>
      </View>
      <IconSymbol name="chevron.right" size={16} color="#94a3b8" />
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: "#f8fafc" },
  guestContainer: { flex: 1, justifyContent: "center", alignItems: "center", backgroundColor: "#f8fafc", padding: 20 },
  guestContent: { alignItems: "center", width: "100%" },
  guestIconContainer: { marginBottom: 24, padding: 20, backgroundColor: "#ffffff", borderRadius: 60, shadowColor: "#000", shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 12, elevation: 5 },
  guestTitle: { fontSize: 24, fontWeight: "bold", color: "#0f172a", marginBottom: 12 },
  guestMessage: { fontSize: 16, color: "#64748b", textAlign: "center", marginBottom: 32, lineHeight: 24 },
  loginButton: { backgroundColor: "#0052cc", paddingVertical: 16, paddingHorizontal: 48, borderRadius: 16, shadowColor: "#0052cc", shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.2, shadowRadius: 8, elevation: 4 },
  loginText: { color: "#ffffff", fontSize: 16, fontWeight: "700" },

  header: { flexDirection: "row", alignItems: "center", padding: 24, paddingTop: 40 },
  avatarContainer: { width: 64, height: 64, borderRadius: 32, backgroundColor: "#0052cc", justifyContent: "center", alignItems: "center", shadowColor: "#0052cc", shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.2, shadowRadius: 8, elevation: 4 },
  avatarText: { color: "#ffffff", fontSize: 28, fontWeight: "bold" },
  userInfo: { marginLeft: 16 },
  userName: { fontSize: 20, fontWeight: "bold", color: "#0f172a" },
  userEmail: { fontSize: 14, color: "#64748b", marginTop: 2 },

  cardWrapper: { paddingHorizontal: 20, marginBottom: 32 },
  loyaltyCard: { borderRadius: 24, padding: 24, height: 160, justifyContent: "space-between", shadowColor: "#000", shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.15, shadowRadius: 20, elevation: 10 },
  cardHeader: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  rankBadge: { flexDirection: "row", alignItems: "center", backgroundColor: "rgba(255,255,255,0.2)", paddingVertical: 6, paddingHorizontal: 12, borderRadius: 20 },
  rankText: { color: "#ffffff", fontSize: 12, fontWeight: "bold", marginLeft: 6 },
  appName: { color: "rgba(255,255,255,0.6)", fontSize: 12, fontWeight: "600" },
  pointsContainer: { marginTop: 12 },
  pointsLabel: { color: "rgba(255,255,255,0.8)", fontSize: 14 },
  pointsValue: { color: "#ffffff", fontSize: 32, fontWeight: "bold", marginTop: 4 },
  pointsUnit: { fontSize: 16, fontWeight: "normal", opacity: 0.8 },

  section: { paddingHorizontal: 20, marginBottom: 24 },
  sectionTitle: { fontSize: 18, fontWeight: "bold", color: "#0f172a", marginBottom: 16 },
  menuGrid: { flexDirection: "row", justifyContent: "space-between" },
  menuCardWrapper: { width: "31%" },
  menuCard: { backgroundColor: "#ffffff", borderRadius: 20, padding: 16, alignItems: "center", shadowColor: "#000", shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 8, elevation: 2 },
  menuIconContainer: { width: 48, height: 48, borderRadius: 24, backgroundColor: "#f0f7ff", justifyContent: "center", alignItems: "center", marginBottom: 12 },
  menuLabel: { fontSize: 12, fontWeight: "600", color: "#334155" },

  settingsList: { backgroundColor: "#ffffff", borderRadius: 24, overflow: "hidden", shadowColor: "#000", shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 8, elevation: 2 },
  settingItem: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", padding: 16, borderBottomWidth: 1, borderBottomColor: "#f1f5f9" },
  settingLeft: { flexDirection: "row", alignItems: "center" },
  settingIconContainer: { width: 36, height: 36, borderRadius: 10, backgroundColor: "#f8fafc", justifyContent: "center", alignItems: "center", marginRight: 12 },
  settingLabel: { fontSize: 15, color: "#334155", fontWeight: "500" },

  logoutButton: { flexDirection: "row", alignItems: "center", padding: 16, marginTop: 8 },
  logoutText: { color: "#ef4444", fontSize: 15, fontWeight: "600", marginLeft: 12 },
});
