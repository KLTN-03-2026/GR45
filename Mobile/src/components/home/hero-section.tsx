import MaterialIcons from "@expo/vector-icons/MaterialIcons";
import { ImageBackground } from "expo-image";
import { StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

export function HeroSection() {
  const insets = useSafeAreaInsets();
  return (
    <ImageBackground
      source={{
        uri: "https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&q=80&w=800&h=600",
      }}
      style={[styles.container, { paddingTop: Math.max(insets.top, 20) }]}
    >
      <View style={styles.overlay} />

      {/* Top Header */}
      <View style={styles.topHeader}>
        <View style={styles.logoRow}>
          <MaterialIcons name="directions-bus" size={24} color="#ffffff" />
          <Text style={styles.logoText}>VigilantBus</Text>
        </View>
        <MaterialIcons name="notifications" size={24} color="#ffffff" />
      </View>

      {/* Hero Text */}
      <View style={styles.heroTextContainer}>
        <Text style={styles.badge}>VIGILANT INTELLIGENCE</Text>
        <Text style={styles.title}>An toàn trên mỗi</Text>
        <Text style={styles.title}>dặm đường</Text>
      </View>
    </ImageBackground>
  );
}

const styles = StyleSheet.create({
  container: {
    width: "100%",
    height: 320,
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(0,34,102,0.6)",
  },
  topHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 20,
    marginBottom: 40,
  },
  logoRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  logoText: {
    color: "#ffffff",
    fontSize: 20,
    fontWeight: "800",
    fontStyle: "italic",
  },
  heroTextContainer: {
    paddingHorizontal: 20,
    gap: 8,
  },
  badge: {
    color: "#e2e8f0",
    fontSize: 12,
    fontWeight: "700",
    letterSpacing: 1,
    marginBottom: 4,
  },
  title: {
    color: "#ffffff",
    fontSize: 32,
    fontWeight: "800",
    lineHeight: 38,
  },
});
