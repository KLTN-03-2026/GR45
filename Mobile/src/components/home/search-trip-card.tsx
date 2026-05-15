import clientApi from "@/src/services/client-api";
import MaterialIcons from "@expo/vector-icons/MaterialIcons";
import React from "react";
import { useState } from "react";
import {
    ActivityIndicator,
    FlatList,
    Modal,
    Pressable,
    StyleSheet,
    Text,
    View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import DateTimePicker from '@react-native-community/datetimepicker';
import { format } from 'date-fns';
import { vi } from 'date-fns/locale';
import { Platform } from 'react-native';

export type Province = {
  id: number;
  ma_tinh_thanh: string;
  ten_tinh_thanh: string;
  created_at: string | null;
  updated_at: string | null;
  ma_tinh_thanh_2: string;
};

type SearchTripCardProps = {
  from?: string;
  to?: string;
  date?: Date;
  onPressSearch?: (params: { from: string, to: string, date: string }) => void;
};

export function SearchTripCard({
  from = "Hà Nội",
  to = "Hải Phòng",
  date = new Date(),
  onPressSearch,
}: SearchTripCardProps) {
  const [provinces, setProvinces] = useState<Province[]>([]);
  const [loading, setLoading] = useState(false);

  const [fromCity, setFromCity] = useState<string>(from);
  const [toCity, setToCity] = useState<string>(to);
  const [searchDate, setSearchDate] = useState<Date>(date);
  const [showDatePicker, setShowDatePicker] = useState(false);

  const [modalVisible, setModalVisible] = useState(false);
  const [selectingType, setSelectingType] = useState<"from" | "to" | null>(
    null,
  );

  const fetchProvinces = async () => {
    if (provinces.length > 0) return;
    try {
      setLoading(true);
      const response = await clientApi.getProvinces();
      
      if (response && response.data) {
        // Tuỳ thuộc vào cấu trúc trả về của backend (có bọc trong data hay không)
        const provinceData = Array.isArray(response.data) 
          ? response.data 
          : (response.data.data || []);
        
        setProvinces(provinceData);
      }
    } catch (err: any) {
      console.log("Lỗi tải tỉnh thành:", err.response?.data || err.message);
      // Mock data based on your json struct
      setProvinces([
        {
          id: 1,
          ma_tinh_thanh: "01",
          ten_tinh_thanh: "Hà Nội",
          created_at: null,
          updated_at: null,
          ma_tinh_thanh_2: "HN",
        },
        {
          id: 21,
          ma_tinh_thanh: "48",
          ten_tinh_thanh: "Thành phố Đà Nẵng",
          created_at: null,
          updated_at: null,
          ma_tinh_thanh_2: "DNG",
        },
        {
          id: 33,
          ma_tinh_thanh: "92",
          ten_tinh_thanh: "Thành phố Cần Thơ",
          created_at: null,
          updated_at: null,
          ma_tinh_thanh_2: "CT",
        },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const openPicker = (type: "from" | "to") => {
    setSelectingType(type);
    setModalVisible(true);
    fetchProvinces();
  };

  const handleSelectProvince = (province: Province) => {
    if (selectingType === "from") {
      setFromCity(province.ten_tinh_thanh);
    } else {
      setToCity(province.ten_tinh_thanh);
    }
    setModalVisible(false);
  };

  const handleSwap = () => {
    const temp = fromCity;
    setFromCity(toCity);
    setToCity(temp);
  };

  const handleDateChange = (event: any, selected?: Date) => {
    setShowDatePicker(Platform.OS === 'ios'); // iOS stays open, android closes automatically
    if (selected) {
      setSearchDate(selected);
    }
  };

  const onSubmit = () => {
    if (onPressSearch) {
      onPressSearch({
         from: fromCity,
         to: toCity,
         date: format(searchDate, 'yyyy-MM-dd')
      });
    }
  };

  const renderLocationModal = () => (
    <Modal
      visible={modalVisible}
      animationType="slide"
      presentationStyle="pageSheet"
    >
      <SafeAreaView style={styles.modalContainer}>
        <View style={styles.modalHeader}>
          <Pressable
            onPress={() => setModalVisible(false)}
            style={styles.closeBtn}
          >
            <MaterialIcons name="close" size={24} color="#0f172a" />
          </Pressable>
          <Text style={styles.modalTitle}>
            {selectingType === "from" ? "Chọn điểm đi" : "Chọn điểm đến"}
          </Text>
          <View style={{ width: 24 }} />
        </View>

        {loading ? (
          <View style={styles.centerContainer}>
            <ActivityIndicator size="large" color="#0052cc" />
            <Text style={{ marginTop: 8, color: "#64748b" }}>
              Đang tải danh sách...
            </Text>
          </View>
        ) : (
          <FlatList
            data={provinces}
            keyExtractor={(item) => item.id.toString()}
            contentContainerStyle={styles.listContent}
            renderItem={({ item }) => (
              <Pressable
                style={styles.provinceItem}
                onPress={() => handleSelectProvince(item)}
              >
                <MaterialIcons name="location-city" size={24} color="#64748b" />
                <Text style={styles.provinceText}>{item.ten_tinh_thanh}</Text>
              </Pressable>
            )}
          />
        )}
      </SafeAreaView>
    </Modal>
  );

  return (
    <View style={styles.card}>
      <View style={styles.locationRow}>
        <View style={styles.locationCol}>
          <Text style={styles.label}>ĐIỂM ĐI</Text>
          <Pressable style={styles.inputBox} onPress={() => openPicker("from")}>
            <MaterialIcons name="location-on" size={20} color="#0052cc" />
            <Text style={styles.inputText} numberOfLines={1}>
              {fromCity}
            </Text>
          </Pressable>
        </View>

        <View style={styles.locationCol}>
          <Text style={styles.label}>ĐIỂM ĐẾN</Text>
          <Pressable style={styles.inputBox} onPress={() => openPicker("to")}>
            <MaterialIcons name="near-me" size={20} color="#0052cc" />
            <Text style={styles.inputText} numberOfLines={1}>
              {toCity}
            </Text>
          </Pressable>
        </View>

        <View style={styles.swapButtonWrapper}>
          <Pressable style={styles.swapButton} onPress={handleSwap}>
            <MaterialIcons name="swap-horiz" size={24} color="#ffffff" />
          </Pressable>
        </View>
      </View>

      <View style={styles.dateContainer}>
        <Text style={styles.label}>NGÀY KHỞI HÀNH</Text>
        <Pressable style={styles.inputBoxDate} onPress={() => setShowDatePicker(true)}>
          <MaterialIcons name="calendar-today" size={20} color="#0052cc" />
          <Text style={styles.inputText}>{format(searchDate, 'EEEE, dd/MM/yyyy', { locale: vi })}</Text>
        </Pressable>
      </View>

      {showDatePicker && (
        <DateTimePicker
          value={searchDate}
          mode="date"
          display={Platform.OS === 'ios' ? 'spinner' : 'default'}
          minimumDate={new Date()}
          onChange={handleDateChange}
        />
      )}

      <Pressable style={styles.submitButton} onPress={onSubmit}>
        <Text style={styles.submitText}>Tìm Chuyến Ngay</Text>
      </Pressable>

      {renderLocationModal()}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: "#ffffff",
    borderRadius: 16,
    padding: 20,
    marginTop: -80,
    marginHorizontal: 16,
    shadowColor: "#0f172a",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 5,
  },
  locationRow: {
    flexDirection: "row",
    gap: 16,
  },
  locationCol: {
    flex: 1,
    gap: 8,
  },
  label: {
    fontSize: 12,
    fontWeight: "700",
    color: "#64748b",
    textTransform: "uppercase",
  },
  inputBox: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#f4f7fe",
    borderRadius: 8,
    paddingHorizontal: 8,
    marginLeft: 6,
    height: 48,
    gap: 6,
  },
  inputBoxDate: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#f4f7fe",
    borderRadius: 8,
    paddingHorizontal: 12,
    height: 48,
    gap: 10,
  },
  inputText: {
    flex: 1,
    fontSize: 14,
    fontWeight: "600",
    color: "#0f172a",
  },
  swapButtonWrapper: {
    position: "absolute",
    left: "50%",
    top: 25,
    marginLeft: -20,
    zIndex: 10,
    elevation: 10,
  },
  swapButton: {
    backgroundColor: "#0052cc",
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: "center",
    alignItems: "center",
    shadowColor: "#0052cc",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 4,
  },
  dateContainer: {
    marginTop: 20,
    gap: 8,
  },
  submitButton: {
    backgroundColor: "#0047b3",
    height: 52,
    borderRadius: 8,
    justifyContent: "center",
    alignItems: "center",
    marginTop: 24,
  },
  submitText: {
    color: "#ffffff",
    fontSize: 16,
    fontWeight: "700",
  },
  modalContainer: {
    flex: 1,
    backgroundColor: "#ffffff",
  },
  modalHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: "#e2e8f0",
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#0f172a",
  },
  closeBtn: {
    padding: 4,
  },
  centerContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  listContent: {
    padding: 16,
    gap: 12,
  },
  provinceItem: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#f8fafc",
    padding: 16,
    borderRadius: 12,
    gap: 12,
  },
  provinceText: {
    fontSize: 16,
    fontWeight: "500",
    color: "#1e293b",
  },
});
